package com.example.myapplication;

import com.example.myapplication.model.ItemDetail;
import com.google.gson.annotations.SerializedName;
import java.util.List;
public class ItemDetailResponse {
    private boolean success;
    private ItemDetail data;

    public boolean isSuccess() {
        return success;
    }

    public ItemDetail getData() {
        return data;
    }
}


