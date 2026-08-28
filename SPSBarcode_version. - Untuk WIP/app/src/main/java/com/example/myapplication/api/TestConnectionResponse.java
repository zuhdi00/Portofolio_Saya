package com.example.myapplication.api;

import com.google.gson.annotations.SerializedName;
import java.util.List;

public class TestConnectionResponse {
    @SerializedName("timestamp")
    public String timestamp;

    @SerializedName("tests")
    public Tests tests;

    @SerializedName("summary")
    public Summary summary;

    public static class Tests {
        // Tambahkan field sesuai kebutuhan
    }

    public static class Summary {
        @SerializedName("overall_status")
        public String overallStatus;
        @SerializedName("recommendations")
        public List<String> recommendations;
        // dst.
    }
}