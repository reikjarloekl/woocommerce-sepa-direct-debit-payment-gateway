CREATE DATABASE simplecam CHARACTER SET utf8;
CREATE USER 'sc_front'@'localhost' IDENTIFIED BY 'DuLcBrq01NIveYWYTw8D';
GRANT ALL PRIVILEGES ON simplecam.* TO 'sc_front'@'localhost';

CREATE USER 'sc_smtp'@'localhost' IDENTIFIED BY 'k4VczgPtBwpHVdjFeFvi';
GRANT SELECT ON simplecam.* TO 'sc_smtp'@'localhost';
GRANT INSERT ON simplecam.front_image TO 'sc_smtp'@'localhost';

flush privileges;
