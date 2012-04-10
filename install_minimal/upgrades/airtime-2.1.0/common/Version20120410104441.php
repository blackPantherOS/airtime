<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class Version20120410104441 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        //add temp columns for changing bitrate and sample rate to integers.
        $this->_addSql("ALTER TABLE cc_files ADD temp_br integer");
        $this->_addSql("ALTER TABLE cc_files ADD temp_sr integer");
        
        $this->_addSql("UPDATE cc_files SET temp_br = bit_rate::integer");
        $this->_addSql("UPDATE cc_files SET temp_sr = sample_rate::integer");
        
        $this->_addSql("ALTER TABLE cc_files DROP COLUMN sample_rate");
        $this->_addSql("ALTER TABLE cc_files DROP COLUMN bit_rate");
        
        $this->_addSql("ALTER TABLE cc_files RENAME COLUMN temp_sr TO sample_rate");
        $this->_addSql("ALTER TABLE cc_files RENAME COLUMN temp_br TO bit_rate");
    }
    
    public function down(Schema $schema)
    {

    }
}