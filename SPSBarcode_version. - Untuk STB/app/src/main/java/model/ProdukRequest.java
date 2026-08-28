package model;

public class ProdukRequest {
    private String nama_produk;

    public ProdukRequest(String nama_produk) {
        this.nama_produk = nama_produk;
    }

    public String getNama_produk() {
        return nama_produk;
    }

    public void setNama_produk(String nama_produk) {
        this.nama_produk = nama_produk;
    }
}
