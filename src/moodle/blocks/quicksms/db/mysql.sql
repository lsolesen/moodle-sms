create table prefix_block_quicksms_log
   ( id int(10) unsigned not null auto_increment,
     courseid int(10) unsigned not null,
     userid int(10) unsigned not null,
     mailto text not null,
     subject varchar(255) not null,
     message text not null,
     attachment varchar(255) not null,
     format tinyint(3) unsigned not null default 1,
     timesent int(10) unsigned not null,
     PRIMARY KEY  (id)
    );