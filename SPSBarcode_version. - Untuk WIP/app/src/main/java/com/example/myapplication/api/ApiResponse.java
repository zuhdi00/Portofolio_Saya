package com.example.myapplication.api;

import com.google.gson.annotations.SerializedName;
import java.util.List;

public class ApiResponse {
    @SerializedName("status")
    private String status;

    @SerializedName("data")
    private List<ApiData> data;

    public String getStatus() {
        return status;
    }

    public List<ApiData> getData() {
        return data;
    }
}
