# API Redirector

This project is an API redirector that handles incoming API requests and redirects them from specified IP addresses to the domain `supracor.co.id`. It is built using Spring Boot and provides a simple way to manage API requests that need to be redirected.

## Project Structure

The project is structured as follows:

```
api-redirector
├── src
│   ├── main
│   │   ├── java
│   │   │   └── com
│   │   │       └── supracor
│   │   │           └── redirector
│   │   │               ├── ApiRedirectController.java
│   │   │               └── RedirectorApplication.java
│   │   └── resources
│   │       ├── application.properties
│   │       └── README.md
├── build.gradle
├── README.md
└── .gitignore
```

## Setup Instructions

1. **Clone the Repository**
   ```bash
   git clone <repository-url>
   cd api-redirector
   ```

2. **Build the Project**
   Ensure you have Gradle installed, then run:
   ```bash
   ./gradlew build
   ```

3. **Run the Application**
   You can run the application using:
   ```bash
   ./gradlew bootRun
   ```

4. **Access the API**
   The API will be available at `http://localhost:8080`. You can send requests to this endpoint, and it will redirect them as configured.

## Usage Guidelines

- The API will redirect requests from specified IP addresses to `supracor.co.id`.
- Ensure that your application properties are correctly set up in `src/main/resources/application.properties` for any specific configurations you may need.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.