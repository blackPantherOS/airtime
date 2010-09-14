CREATE SEQUENCE file_id_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 1000000
  CACHE 1;

ALTER TABLE ls_files
    ALTER COLUMN id 
        SET DEFAULT NEXTVAL('file_id_seq');

DROP TABLE ls_struct CASCADE;
DROP TABLE ls_tree CASCADE;
DROP TABLE ls_classes CASCADE;
DROP TABLE ls_cmemb CASCADE;

DROP SEQUENCE ls_struct_id_seq_seq;
DROP SEQUENCE ls_tree_id_seq_seq;

DROP TABLE as_tree CASCADE;
DROP TABLE as_struct CASCADE;
DROP TABLE as_classes CASCADE;
DROP TABLE as_cmemb CASCADE;

DROP SEQUENCE as_struct_id_seq_seq;
DROP SEQUENCE as_tree_id_seq_seq;

ALTER TABLE cc_files
   ADD COLUMN track_title character varying(512);
ALTER TABLE cc_files
   ADD COLUMN artist_name character varying(512);
ALTER TABLE cc_files
   ADD COLUMN bit_rate character varying(32);
ALTER TABLE cc_files
   ADD COLUMN sample_rate character varying(32);
ALTER TABLE cc_files
   ADD COLUMN format character varying(128);
ALTER TABLE cc_files
   ADD COLUMN length character (16);
ALTER TABLE cc_files
   ADD COLUMN album_title character varying(512);
ALTER TABLE cc_files
   ADD COLUMN genre character varying(64);
ALTER TABLE cc_files
   ADD COLUMN comments text;
ALTER TABLE cc_files
   ADD COLUMN "year" character varying(16);
ALTER TABLE cc_files
   ADD COLUMN track_number integer;
ALTER TABLE cc_files
   ADD COLUMN channels integer;
ALTER TABLE cc_files
   ADD COLUMN url character varying(1024);

   
   



   