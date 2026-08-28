package com.example.urlredirector;

import okhttp3.Interceptor;
import okhttp3.Request;
import okhttp3.Response;

import java.io.IOException;
import java.util.regex.Pattern;

public class RedirectInterceptor implements Interceptor {

    private static final String REDIRECT_DOMAIN = "https://supracor.co.id";
    private static final Pattern IP_ADDRESS_PATTERN = Pattern.compile(
            "^(http://|https://)?(\\d{1,3}\\.){3}\\d{1,3}(:\\d+)?(/.*)?$"
    );

    @Override
    public Response intercept(Chain chain) throws IOException {
        Request originalRequest = chain.request();
        String originalUrl = originalRequest.url().toString();

        if (IP_ADDRESS_PATTERN.matcher(originalUrl).find()) {
            String newUrl = REDIRECT_DOMAIN + originalUrl.substring(originalUrl.indexOf("/", originalUrl.indexOf("://") + 3));
            Request newRequest = originalRequest.newBuilder()
                    .url(newUrl)
                    .build();
            return chain.proceed(newRequest);
        }

        return chain.proceed(originalRequest);
    }
}