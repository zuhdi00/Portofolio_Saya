package com.example.myapplication.api;

import retrofit2.Call;
import retrofit2.http.GET;
import retrofit2.http.POST;
import retrofit2.http.FormUrlEncoded;
import retrofit2.http.Field;
import retrofit2.http.Query;
import okhttp3.ResponseBody;

import com.example.myapplication.model.ServerResponse;
import com.example.myapplication.ItemDetailResponse;
import com.example.myapplication.model.TestResponses;
import com.example.myapplication.api.ApiResponse;
import com.example.myapplication.api.TestConnectionResponse;

public interface ApiService {

    // Ambil data utama
    @GET("proxy_mobile.php")
    Call<ApiResponse> fetchData(@Query("path") String path);

    // Ambil detail item
    @GET("proxy_mobile.php")
    Call<ItemDetailResponse> getItemDetail(
        @Query("path") String path,
        @Query("barcode") String barcode
    );

    // Ambil detail item (raw) — mengembalikan response body JSON mentah
    @GET("proxy_mobile.php")
    Call<ResponseBody> getItemDetailRaw(
        @Query("path") String path,
        @Query("barcode") String barcode
    );

    // Test koneksi
    @GET("proxy_mobile.php")
    Call<TestConnectionResponse> testConnection(@Query("path") String path);

    // Update Qty
    @FormUrlEncoded
    @POST("proxy_mobile.php?path=api_update_qty_WIP_1.php")
    Call<ServerResponse> updateQty(
        @Field("cNoSTB") String cNoSTB,
        @Field("nQty") double nQty
    );

    // Update Rak
    @FormUrlEncoded
    @POST("proxy_mobile.php?path=api_update_rak_WIP_1.php")
    Call<ServerResponse> updateRak(
        @Field("cNoSTB") String cNoSTB,
        @Field("cRak") String cRak
    );

    // Update Posted
    @FormUrlEncoded
    @POST("proxy_mobile.php?path=api_update_posted_WIP.php")
    Call<ServerResponse> updatePosted(
        @Field("cNoSTB") String cNoSTB,
        @Field("lPosted") String lPosted,
        @Field("cKeterangan") String cKeterangan
    );

    // Approve STB
    @FormUrlEncoded
    @POST("proxy_mobile.php?path=api_approve_stb_WIP_1.php")
    Call<ServerResponse> approveStb(
        @Field("cNoSTB") String cNoSTB,
        @Field("cKeterangan") String cKeterangan
    );

        // Approve STB dengan pengecekan OP+toleransi (baru)
        @FormUrlEncoded
        @POST("proxy_mobile.php?path=api_approve_stb_check_WIP.php")
        Call<ServerResponse> approveStbCheck(
            @Field("cNoSTB") String cNoSTB,
            @Field("cKeterangan") String cKeterangan,
            @Field("cTipe") String cTipe,
            @Field("nBerat2") String nBerat2,
            @Field("nQtyPalet") String nQtyPalet,
            @Field("cShift") String cShift,
            @Field("cKashift") String cKashift
        );

    // Delete Barcode
    @FormUrlEncoded
    @POST("proxy_mobile.php?path=api_delete_barcode_WIP.php")
    Call<ServerResponse> deleteBarcode(
        @Field("barcode") String barcode
    );

    // Ambil detail item by cNoOpValue
    @GET("proxy_mobile.php")
    Call<ItemDetailResponse> getItemDetailByCNoOpValue(
        @Query("target") String target, // "api_get_item_detail_v2.php"
        @Query("cNoOpValue") String cNoOpValue
    );
}
