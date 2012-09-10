<?php

class Application_Model_Datatables
{
    private static function buildWhereClauseForAdvancedSearch($dbname2searchTerm)
    {
        $where['clause'] = array();
        $where['params'] = array();
        foreach ($dbname2searchTerm as $dbname=>$term) {
            $isRange = false;
            if (strstr($term, '~')) {
                $info = explode('~', $term);
                if ($dbname == 'utime' || $dbname == 'mtime') {
                    $input1 = isset($info[0])?Application_Common_DateHelper::ConvertToUtcDateTimeString($info[0]):null;
                    $input2 = isset($info[1])?Application_Common_DateHelper::ConvertToUtcDateTimeString($info[1]):null;
                } else {
                    $input1 = isset($info[0])?$info[0]:null;
                    $input2 = isset($info[1])?$info[1]:null;
                }
                $isRange = true;
            } else {
                $input1 = $term;
            }

            if ($isRange) {
                $sub = array();
                if ($input1 != null) {
                    $sub[] = $dbname." >= :" . $dbname . "1";
                }
                if ($input2 != null) {
                    $sub[] = $dbname." <= :" . $dbname . "2";
                }
                if (!empty($sub)) {
                    $where['clause'][$dbname] = "(".implode(' AND ', $sub).")";
                    if ($input1 != null) {
                        $where['params'][$dbname."1"] = $input1;
                    }
                    if ($input2 != null) {
                        $where['params'][$dbname."2"] = $input2;
                    }
                }
            } else {
                if (trim($input1) !== "") {
                    $where['clause'][$dbname] = $dbname." ILIKE :" . $dbname."1";
                    $where['params'][$dbname."1"] = "%".$input1."%";
                }
            }
        }
        return $where;
    }
    /*
     * query used to return data for a paginated/searchable datatable.
     */
    public static function findEntries($con, $displayColumns, $fromTable,
        $data, $dataProp = "aaData")
    {
        $librarySetting = Application_Model_Preference::getCurrentLibraryTableSetting();
        //$displayColumns[] = 'owner';

        // map that maps original column position to db name
        $current2dbname = array();
        // array of search terms
        $orig2searchTerm = array();
        foreach ($data as $key => $d) {
            if (strstr($key, "mDataProp_")) {
                list($dump, $index) = explode("_", $key);
                $current2dbname[$index] = $d;
            } elseif (strstr($key, "sSearch_")) {
                list($dump, $index) = explode("_", $key);
                $orig2searchTerm[$index] = $d;
            }
        }
        // map that maps current column position to original position
        $current2orig = $librarySetting['ColReorder'];

        // map that maps dbname to searchTerm
        $dbname2searchTerm = array();
        foreach ($current2dbname as $currentPos=>$dbname) {
            $dbname2searchTerm[$dbname] = $orig2searchTerm[$current2orig[$currentPos]];
        }

        $where = array();
        /* Holds the parameters for binding after the
         * statement has been prepared
         */
        $params = array();

        $advancedWhere = self::buildWhereClauseForAdvancedSearch($dbname2searchTerm);
        if (!empty($advancedWhere['clause'])) {
            $where[] = join(" AND ", $advancedWhere['clause']);
            $params = $advancedWhere['params'];
        }

        if ($data["sSearch"] !== "") {
            $searchTerms = explode(" ", $data["sSearch"]);
        }

        $selectorCount = "SELECT COUNT(*) ";
        $selectorRows = "SELECT ".join(",", $displayColumns)." ";

        $sql = $selectorCount." FROM ".$fromTable;
        $sqlTotalRows = $sql;


        if (isset($searchTerms)) {
            $searchCols = array();
            for ($i = 0; $i < $data["iColumns"]; $i++) {
                if ($data["bSearchable_".$i] == "true") {
                    $searchCols[] = $data["mDataProp_{$i}"];
                }
            }

            $outerCond = array();
            $simpleWhere = array();

            foreach ($searchTerms as $term) {
                $innerCond = array();

                foreach ($searchCols as $col) {
                    $simpleWhere['clause']["simple_".$col] = "{$col}::text ILIKE :simple_".$col;
                    $simpleWhere['params']["simple_".$col] = "%".$term."%"; 
                }
                $outerCond[] = "(".implode(" OR ", $simpleWhere['clause']).")";
            }
            $where[] = "(" .implode(" AND ", $outerCond). ")";
            $params = array_merge($params, $simpleWhere['params']);
        }
        // End Where clause

        // Order By clause
        $orderby = array();
        for ($i = 0; $i < $data["iSortingCols"]; $i++) {
            $num = $data["iSortCol_".$i];
            $orderby[] = $data["mDataProp_{$num}"]." ".$data["sSortDir_".$i];
        }
        $orderby[] = "id";
        $orderby = join("," , $orderby);
        // End Order By clause

        $displayLength = intval($data["iDisplayLength"]);
        $needToBind = false;
        if (count($where) > 0) {
            $needToBind = true;
            $where = join(" OR ", $where);
            $sql = $selectorCount." FROM ".$fromTable." WHERE ".$where;
            $sqlTotalDisplayRows = $sql;

            $sql = $selectorRows." FROM ".$fromTable." WHERE ".$where." ORDER BY ".$orderby;

            //limit the results returned.
            if ($displayLength !== -1) {
                $sql .= " OFFSET ".$data["iDisplayStart"]." LIMIT ".$displayLength;
            }
        } else {
            $sql = $selectorRows." FROM ".$fromTable." ORDER BY ".$orderby;

            //limit the results returned.
            if ($displayLength !== -1) {
                $sql .= " OFFSET ".$data["iDisplayStart"]." LIMIT ".$displayLength;
            }
        }
        try {
            
            $r = $con->query($sqlTotalRows);
            $totalRows = $r->fetchColumn(0);

            if (isset($sqlTotalDisplayRows)) {
                $totalDisplayRows = Application_Common_Database::prepareAndExecute($sqlTotalDisplayRows, $params, 'column');
            } else {
                $totalDisplayRows = $totalRows;
            }

            //TODO
            if ($needToBind) {
                $results = Application_Common_Database::prepareAndExecute($sql, $params);
            } else {
                $stmt = $con->query($sql);
                $stmt->setFetchMode(PDO::FETCH_ASSOC);
                $results = $stmt->fetchAll();
            }
            
            // we need to go over all items and fix length for playlist
            // in case the playlist contains dynamic block
            foreach ($results as &$r) {
                //this function is also called for Manage Users so in
                //this case there will be no 'ftype'
                if (isset($r['ftype'])) {
                    if ($r['ftype'] == 'playlist') {
                        $pl = new Application_Model_Playlist($r['id']);
                        $r['length'] = $pl->getLength();
                    } elseif ($r['ftype'] == "block") {
                        $bl = new Application_Model_Block($r['id']);
                        if ($bl->isStatic()) {
                            $r['bl_type'] = 'static';
                        } else {
                            $r['bl_type'] = 'dynamic';
                        }
                        $r['length'] = $bl->getLength();
                    }
                }
            }
        } catch (Exception $e) {
            Logging::debug($e->getMessage());
        }
        return array(
            "sEcho"                => intval($data["sEcho"]),
            "iTotalDisplayRecords" => intval($totalDisplayRows),
            "iTotalRecords"        => intval($totalRows),
            $dataProp              => $results
        );
    }
}
