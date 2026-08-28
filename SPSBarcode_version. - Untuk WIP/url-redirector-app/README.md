# URL Redirector App

## Overview
The URL Redirector App is designed to intercept HTTP requests and redirect any requests containing IP addresses to the domain `supracor.co.id`. This application utilizes Retrofit for network calls and provides a user-friendly interface for managing URL redirection.

## Features
- Intercepts HTTP requests to check for IP addresses.
- Redirects requests containing IP addresses to `supracor.co.id`.
- Simple user interface for inputting URLs and viewing redirection results.

## Project Structure
```
url-redirector-app
├── src
│   ├── main
│   │   ├── java
│   │   │   └── com
│   │   │       └── example
│   │   │           └── urlredirector
│   │   │               ├── RedirectInterceptor.java
│   │   │               ├── RedirectApiService.java
│   │   │               └── MainActivity.java
│   │   └── res
│   │       └── xml
│   │           └── network_security_config.xml
├── build.gradle
├── settings.gradle
└── README.md
```

## Setup Instructions
1. Clone the repository to your local machine.
2. Open the project in your preferred IDE.
3. Ensure you have the necessary dependencies specified in the `build.gradle` file.
4. Build the project to resolve dependencies.

## Usage
- Launch the application.
- Input a URL containing an IP address in the designated field.
- The application will automatically redirect the request to `supracor.co.id`.

## Dependencies
- Retrofit for network operations.
- AndroidX libraries for UI components.

## License
This project is licensed under the MIT License. See the LICENSE file for more details.