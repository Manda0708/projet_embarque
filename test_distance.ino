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

void get_distance() {

  digitalWrite(TRIG, LOW);
  delayMicroseconds(2);

  digitalWrite(TRIG, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG, LOW);

  long duree = pulseIn(ECHO, HIGH);
  float distance = duree * 0.034 / 2;

  Serial.print("Distance : ");
  Serial.print(distance);
  Serial.println(" cm");
 
  if (distance < 5) {
    digitalWrite(led, HIGH); // allumer LED
    Serial.println("!!!!! < 5cm");
  }
  else {
    digitalWrite(led, LOW); 
  }
}

void get_temperature() {
  int lecture = analogRead(capteurTemp);           // lire la valeur analogique (0-1023)
  temperatureC = (lecture * 5.0 * 100.0) / 1023;  // conversion en °C
  Serial.print("Temperature: ");
  Serial.print(temperatureC);
  Serial.println(" °C");
}


void loop(){
  get_temperature();
  get_distance();
  delay(500);
}