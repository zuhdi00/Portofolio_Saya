package com.example.urlredirector;

import android.os.Bundle;
import android.widget.Button;
import android.widget.EditText;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class MainActivity extends AppCompatActivity {

    private EditText urlInput;
    private Button redirectButton;
    private TextView resultText;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        urlInput = findViewById(R.id.urlInput);
        redirectButton = findViewById(R.id.redirectButton);
        resultText = findViewById(R.id.resultText);

        redirectButton.setOnClickListener(v -> redirectUrl());
    }

    private void redirectUrl() {
        String url = urlInput.getText().toString().trim();
        if (url.isEmpty()) {
            Toast.makeText(this, "Please enter a URL", Toast.LENGTH_SHORT).show();
            return;
        }

        RedirectApiService apiService = ApiClient.getClient().create(RedirectApiService.class);
        Call<RedirectResponse> call = apiService.redirectUrl(url);
        call.enqueue(new Callback<RedirectResponse>() {
            @Override
            public void onResponse(Call<RedirectResponse> call, Response<RedirectResponse> response) {
                if (response.isSuccessful() && response.body() != null) {
                    resultText.setText("Redirected to: " + response.body().getRedirectedUrl());
                } else {
                    Toast.makeText(MainActivity.this, "Failed to redirect URL", Toast.LENGTH_SHORT).show();
                }
            }

            @Override
            public void onFailure(Call<RedirectResponse> call, Throwable t) {
                Toast.makeText(MainActivity.this, "Error: " + t.getMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }
}