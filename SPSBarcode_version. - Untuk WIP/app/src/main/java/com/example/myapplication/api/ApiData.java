package com.example.myapplication.api;


import com.google.gson.annotations.SerializedName;

public class ApiData {

    @SerializedName("cNoOp")
    private String noOp;

    @SerializedName("cnm_c")
    private String customer;

    @SerializedName("cnm_brg")
    private String barang;

    public String getNoOp() {
        return noOp;
    }

    public String getCustomer() {
        return customer;
    }

    public String getBarang() {
        return barang;
    }
}

