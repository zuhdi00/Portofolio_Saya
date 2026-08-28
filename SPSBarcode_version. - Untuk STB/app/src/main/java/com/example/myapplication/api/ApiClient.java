package com.example.myapplication.api;

import retrofit2.Retrofit;
import retrofit2.converter.gson.GsonConverterFactory;

public class ApiClient {

<<<<<<< HEAD
    private static final String BASE_URL = "http://36.81.162.214:8081/";
=======
    // Gunakan domain supracor.co.id sebagai base URL
    private static final String BASE_URL = "https://supracor.co.id/";
>>>>>>> 3e81cfb (Update 01 November 2025)
    private static Retrofit retrofit = null;

    public static Retrofit getClient() {
        if (retrofit == null) {
            retrofit = new Retrofit.Builder()
                    .baseUrl(BASE_URL)
                    .addConverterFactory(GsonConverterFactory.create())
                    .build();
        }
        return retrofit;
    }
}
