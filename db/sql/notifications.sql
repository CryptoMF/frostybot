--
-- File generated with SQLiteStudio v3.2.1 on Sat Aug 1 18:24:57 2020
--
-- Text encoding used: System
--
PRAGMA foreign_keys = off;
BEGIN TRANSACTION;

-- Table: notifications
DROP TABLE IF EXISTS notifications;

CREATE TABLE notifications (
    uid      INTEGER      PRIMARY KEY AUTOINCREMENT
                          NOT NULL,
    platform VARCHAR (20) NOT NULL,
    params   TEXT         NOT NULL
);


COMMIT TRANSACTION;
PRAGMA foreign_keys = on;
