package com.supracor.redirector;

import org.springframework.http.HttpHeaders;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestHeader;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.servlet.support.ServletUriComponentsBuilder;

import java.net.URI;

@RestController
public class ApiRedirectController {

    private static final String TARGET_DOMAIN = "http://supracor.co.id";

    @GetMapping("/api/**")
    public ResponseEntity<Void> redirectApi(@RequestHeader(HttpHeaders.HOST) String host) {
        String requestUri = ServletUriComponentsBuilder.fromCurrentRequestUri().toUriString();
        URI redirectUri = URI.create(TARGET_DOMAIN + requestUri.substring(requestUri.indexOf("/api/")));

        return ResponseEntity.status(HttpStatus.FOUND)
                .location(redirectUri)
                .build();
    }
}