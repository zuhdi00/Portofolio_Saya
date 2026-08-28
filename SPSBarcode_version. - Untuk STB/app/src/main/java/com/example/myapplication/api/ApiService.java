package com.example.myapplication.api;

import retrofit2.Call;
import retrofit2.http.GET;
import retrofit2.http.POST;
import retrofit2.http.FormUrlEncoded;
import retrofit2.http.Field;
import retrofit2.http.Query;

import com.example.myapplication.model.ServerResponse;
import com.example.myapplication.ItemDetailResponse;
import com.example.myapplication.model.TestResponses;
<<<<<<< HEAD

public interface ApiService {

    @GET("api_get_data.php")
    Call<ApiResponse> fetchData();

    @GET("api_get_item_detail_29092025.php")
    Call<ItemDetailResponse> getItemDetail(@Query("barcode") String barcode);

    @GET("test_connection.php")
    Call<TestResponses> testConnection();

    @FormUrlEncoded
    @POST("api_update_qty.php")
    Call<ServerResponse> updateQty(
            @Field("cNoSTB") String cNoSTB,
            @Field("nQty") double nQty
    );
    @FormUrlEncoded
    @POST("api_update_rak.php")
    Call<ServerResponse> updateRak(
            @Field("cNoSTB") String cNoSTB,
            @Field("cRak") String cRak
    );
    @FormUrlEncoded
    @POST("api_update_posted.php")
    Call<ServerResponse> updatePosted(
            @Field("cNoSTB") String cNoSTB,
            @Field("lPosted") String lPosted,
            @Field("cKeterangan") String cKeterangan
    );
    @FormUrlEncoded
    @POST("api_approve_stb.php")
=======
import com.example.myapplication.api.ApiResponse;
import com.example.myapplication.api.TestConnectionResponse;

public interface ApiService {

    // Ambil data utama
    @GET("proxy_mobile.php")
    Call<ApiResponse> fetchData(@Query("path") String path);

    // Ambil detail item
    @GET("proxy_mobile.php")
    Call<ItemDetailResponse> getItemDetail(
        @Query("path") String path, // ganti dari "target" ke "path"
        @Query("barcode") String barcode
    );

    // Test koneksi
    @GET("proxy_mobile.php")
    Call<TestConnectionResponse> testConnection(@Query("path") String path);

    // Update Qty
    @FormUrlEncoded
    @POST("proxy_mobile.php?path=api_update_qty.php")
    Call<ServerResponse> updateQty(
        @Field("cNoSTB") String cNoSTB,
        @Field("nQty") double nQty
    );

    // Update Rak
    @FormUrlEncoded
    @POST("proxy_mobile.php?path=api_update_rak.php")
    Call<ServerResponse> updateRak(
        @Field("cNoSTB") String cNoSTB,
        @Field("cRak") String cRak
    );

    // Update Posted
    @FormUrlEncoded
    @POST("proxy_mobile.php?path=api_update_posted.php")
    Call<ServerResponse> updatePosted(
        @Field("cNoSTB") String cNoSTB,
        @Field("lPosted") String lPosted,
        @Field("cKeterangan") String cKeterangan
    );

    // Approve STB
    @FormUrlEncoded
    @POST("proxy_mobile.php?path=api_approve_stb.php")
>>>>>>> 3e81cfb (Update 01 November 2025)
    Call<ServerResponse> approveStb(
        @Field("cNoSTB") String cNoSTB,
        @Field("cKeterangan") String cKeterangan
    );
<<<<<<< HEAD
    @FormUrlEncoded
    @POST("api_delete_barcode.php")
    Call<ServerResponse> deleteBarcode(
        @Field("barcode") String barcode
    );
=======

    // Delete Barcode
    @FormUrlEncoded
    @POST("proxy_mobile.php?path=api_delete_barcode.php")
    Call<ServerResponse> deleteBarcode(
        @Field("barcode") String barcode
    );

    // Ambil detail item by cNoOpValue
    @GET("proxy_mobile.php")
    Call<ItemDetailResponse> getItemDetailByCNoOpValue(
        @Query("target") String target, // "api_get_item_detail_v2.php"
        @Query("cNoOpValue") String cNoOpValue
    );
>>>>>>> 3e81cfb (Update 01 November 2025)
}
