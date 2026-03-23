#include <SoftwareSerial.h>

#define TRIG 9
#define ECHO 10

int led = 8;
int capteurTemp = A0;

float temperatureC;

// RX, TX pour le Bluetooth
SoftwareSerial bluetooth(2, 3);

void setup() {

  Serial.begin(9600);        // pour le PC
  bluetooth.begin(9600);     // pour le module Bluetooth

  pinMode(TRIG, OUTPUT);
  pinMode(ECHO, INPUT);
  pinMode(led, OUTPUT);
}

void loop() {

  int lecture = analogRead(capteurTemp);
  temperatureC = (lecture * 5.0 * 100.0) / 1023;

  // Mesure distance
  digitalWrite(TRIG, LOW);
  delayMicroseconds(2);

  digitalWrite(TRIG, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG, LOW);

  long duree = pulseIn(ECHO, HIGH);
  float distance = duree * 0.034 / 2;

  if (distance < 5) {
    digitalWrite(led, HIGH);
  } 
  else {
    digitalWrite(led, LOW);
  }

  // format envoyé
  String data = "temp=" + String(temperatureC) + ";dist=" + String(distance);

  // envoi PC
  Serial.println(data);

  // envoi Bluetooth
  bluetooth.println(data);

  delay(1000);
}