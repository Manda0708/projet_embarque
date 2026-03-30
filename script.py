import serial
import mysql.connector
from datetime import datetime

# 1️⃣ Configurer le port série
ser = serial.Serial('COM3', 9600, timeout=1)

# 2️⃣ Configurer la connexion MySQL
db = mysql.connector.connect(
    host="localhost",
    user="root",     
    password="",  
    database="capteurs_bd"
)
cursor = db.cursor()

# 3️⃣ Boucle de lecture
try:
    while True:
        line = ser.readline().decode('utf-8').strip()  # lire et décoder la ligne
        if line:
            # Exemple de ligne : "temp=24.5;dist=12.3"
            try:
                parts = line.split(';')
                temp = float(parts[0].split('=')[1])
                hum = float(parts[1].split('=')[1])
                dist = float(parts[2].split('=')[1]) 
                
                # Ajouter timestamp
                now = datetime.now()
                
                # 4️⃣ Insérer dans la base de données
                sql = "INSERT INTO mesures (temp, hum, dist, datetime) VALUES (%s, %s, %s, %s)"
                val = (temp, hum, dist, now)
                cursor.execute(sql, val)
                db.commit()
                
                print(f"[{now}] Temp: {temp}°C, Humidité: {hum}%, Distance: {dist}cm - enregistré en DB")
                
            except Exception as e:
                print("Erreur parsing ligne:", line, e)

except KeyboardInterrupt:
    print("Arrêt du programme")
    ser.close()
    cursor.close()
    db.close()