package com.example.myapplication;

import android.content.Context;
import android.content.SharedPreferences;
import android.util.Log;

import org.json.JSONArray;
import org.json.JSONException;

import java.util.ArrayList;
import java.util.List;

public class ScanHistoryManager {

    private static final String PREF_NAME = "scan_history";
    private static final String KEY_HISTORY = "history_list";

    private final SharedPreferences sharedPreferences;

    public ScanHistoryManager(Context context) {
        sharedPreferences = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
    }

    public void saveScan(String value) {
        try {
            Log.d("ScanHistory", "Saving scan: " + value);
            List<String> history = getHistory();
            if (!history.contains(value)) {
                history.add(0, value); // Tambah ke atas
                saveHistory(history);
            }
        } catch (Exception e) {
            Log.e("ScanHistory", "Error saving scan: " + e.getMessage(), e);
        }
    }

    private void saveHistory(List<String> history) {
        JSONArray jsonArray = new JSONArray();
        for (String item : history) {
            jsonArray.put(item);
        }
        sharedPreferences.edit().putString(KEY_HISTORY, jsonArray.toString()).apply();
    }

    public List<String> getHistory() {
        List<String> result = new ArrayList<>();
        String json = sharedPreferences.getString(KEY_HISTORY, null);
        if (json != null) {
            try {
                JSONArray jsonArray = new JSONArray(json);
                for (int i = 0; i < jsonArray.length(); i++) {
                    result.add(jsonArray.getString(i));
                }
            } catch (JSONException e) {
                Log.e("ScanHistory", "Error reading history JSON: " + e.getMessage(), e);
                // Reset jika data rusak
                sharedPreferences.edit().remove(KEY_HISTORY).apply();
            }
        }
        return result;
    }
}
