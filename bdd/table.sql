create database capteurs_bd;

create table mesures (
    id int primary key auto_increment,
    temp float,
    hum float,
    dist float,
    datetime datetime
);

-- 10 dernières mesures
CREATE VIEW v_last10 AS
    SELECT * FROM mesures ORDER BY id DESC LIMIT 10;

-- Dernière mesure
CREATE VIEW v_last1 AS
    SELECT temp, hum, dist, datetime FROM mesures ORDER BY id DESC LIMIT 1;

-- Stats sur les 10 dernières
CREATE VIEW v_stats_live AS
    SELECT
        MAX(temp) as temp_max, MIN(temp) as temp_min, ROUND(AVG(temp), 1) as temp_avg,
        MAX(hum)  as hum_max,  MIN(hum)  as hum_min,  ROUND(AVG(hum),  1) as hum_avg,
        MAX(dist) as dist_max, MIN(dist) as dist_min,  ROUND(AVG(dist), 1) as dist_avg
    FROM v_last10;

-- Stats sur la dernière heure
CREATE VIEW v_stats_hour AS
    SELECT
        MAX(temp) as temp_max, MIN(temp) as temp_min, ROUND(AVG(temp), 1) as temp_avg,
        MAX(hum)  as hum_max,  MIN(hum)  as hum_min,  ROUND(AVG(hum),  1) as hum_avg,
        MAX(dist) as dist_max, MIN(dist) as dist_min,  ROUND(AVG(dist), 1) as dist_avg
    FROM mesures WHERE datetime >= NOW() - INTERVAL 1 HOUR;

-- Stats sur la dernière semaine
CREATE VIEW v_stats_week AS
    SELECT
        MAX(temp) as temp_max, MIN(temp) as temp_min, ROUND(AVG(temp), 1) as temp_avg,
        MAX(hum)  as hum_max,  MIN(hum)  as hum_min,  ROUND(AVG(hum),  1) as hum_avg,
        MAX(dist) as dist_max, MIN(dist) as dist_min,  ROUND(AVG(dist), 1) as dist_avg
    FROM mesures WHERE datetime >= NOW() - INTERVAL 7 DAY;

-- Alertes (distance < 5 cm)
CREATE VIEW v_alerts AS
    SELECT dist, datetime FROM mesures WHERE dist < 5 ORDER BY id DESC LIMIT 5;