create database capteurs_bd;

create table mesures (  
    id int primary key auto_increment,
    temp float,
    hum float,
    dist float,
    datetime datetime
);
