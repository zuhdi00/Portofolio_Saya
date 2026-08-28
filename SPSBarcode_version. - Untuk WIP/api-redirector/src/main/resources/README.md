# API Redirector

This project is designed to redirect API requests from specific IP addresses to the domain `supracor.co.id`. It utilizes Spring Boot to handle incoming requests and perform the necessary redirection.

## Project Structure

- `src/main/java/com/supracor/redirector/ApiRedirectController.java`: Contains the `ApiRedirectController` class which manages the redirection of API requests.
- `src/main/java/com/supracor/redirector/RedirectorApplication.java`: The main application class that initializes the Spring Boot application.
- `src/main/resources/application.properties`: Configuration properties for the Spring Boot application.
- `src/main/resources/README.md`: Documentation for the API redirector.
- `build.gradle`: Build configuration file for Gradle.
- `README.md`: General information about the project.
- `.gitignore`: Specifies files and directories to be ignored by version control.

## Setup Instructions

1. **Clone the repository**:
   ```
   git clone <repository-url>
   ```

2. **Navigate to the project directory**:
   ```
   cd api-redirector
   ```

3. **Build the project**:
   ```
   ./gradlew build
   ```

4. **Run the application**:
   ```
   ./gradlew bootRun
   ```

## Usage

Once the application is running, it will listen for incoming API requests. Any requests originating from specified IP addresses will be redirected to `supracor.co.id`. 

Make sure to configure the necessary properties in `application.properties` to suit your deployment environment.

## Contributing

Contributions are welcome! Please submit a pull request or open an issue for any enhancements or bug fixes.