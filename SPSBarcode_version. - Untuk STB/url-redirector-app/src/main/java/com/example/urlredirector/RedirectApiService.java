package com.example.urlredirector;

import retrofit2.Call;
import retrofit2.http.GET;
import retrofit2.http.Query;

public interface RedirectApiService {

    @GET("redirect")
    Call<Void> redirectUrl(@Query("url") String url);
}