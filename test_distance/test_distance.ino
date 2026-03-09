#define TRIG 9
#define ECHO 10
int led = 8;
int capteurTemp = A0; // pin analogique où est branché le LM35
float temperatureC;   // variable pour stocker la température


void setup() {
  Serial.begin(9600);
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
  } else {
    digitalWrite(led, LOW);
  }

  // Envoi formaté pour Python
  Serial.print("temp=");
  Serial.print(temperatureC);
  Serial.print(";dist=");
  Serial.println(distance);  // println ajoute automatiquement '\n'

  delay(1000);
}