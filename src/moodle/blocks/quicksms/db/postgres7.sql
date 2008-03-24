create table prefix_block_quicksms_log (
    id serial,
    courseid integer not null default 0,
    userid integer not null default 0,
    mailto text not null default '',
    subject varchar(255) not null default '',
    message text not null default '',
    attachment varchar(255) not null default '',
    format int4 not null default 1,
    timesent integer not null default 0,
    PRIMARY KEY (id)
);