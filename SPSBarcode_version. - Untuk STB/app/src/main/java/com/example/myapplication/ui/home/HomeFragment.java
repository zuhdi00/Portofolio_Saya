package com.example.myapplication.ui.home;

import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;

import androidx.annotation.NonNull;
import androidx.databinding.DataBindingUtil;
import androidx.fragment.app.Fragment;
import androidx.lifecycle.ViewModelProvider;

import com.example.myapplication.HistoryActivity;
import com.example.myapplication.ItemDetailActivity;
import com.example.myapplication.R;
import com.example.myapplication.databinding.FragmentHomeBinding;
import com.example.myapplication.ui.home.HomeViewModel.NavigationEvent;

public class HomeFragment extends Fragment {

    private FragmentHomeBinding binding;
    private HomeViewModel homeViewModel;

    @Override
    public View onCreateView(@NonNull LayoutInflater inflater,
                             ViewGroup container, Bundle savedInstanceState) {

        // Inisialisasi ViewModel
        homeViewModel = new ViewModelProvider(this).get(HomeViewModel.class);

        // Inisialisasi Data Binding
        binding = DataBindingUtil.inflate(inflater, R.layout.fragment_home, container, false);
        binding.setViewModel(homeViewModel);
        binding.setLifecycleOwner(getViewLifecycleOwner());

        // Handle tombol Exit secara manual (karena tidak bisa dari ViewModel)
        binding.btnExit.setOnClickListener(v -> requireActivity().finish());

        // Setup observers
        setupObservers();

        return binding.getRoot();
    }

    private void setupObservers() {
        homeViewModel.getNavigationEvent().observe(getViewLifecycleOwner(), event -> {
            if (event != null) {
                NavigationEvent navigation = event.getContentIfNotHandled();
                if (navigation != null) {
                    handleNavigationEvent(navigation);
                }
                homeViewModel.onNavigationComplete();
            }
        });
    }

    private void handleNavigationEvent(NavigationEvent event) {
        switch (event.getDestination()) {
            case HISTORY:
                startActivity(new Intent(requireActivity(), HistoryActivity.class));
                break;
            case ITEM_DETAIL:
                startActivity(new Intent(requireActivity(), ItemDetailActivity.class));
                break;
        }
    }

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}

/*
package com.example.myapplication.ui.home;


import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Button;

import androidx.annotation.NonNull;
import androidx.fragment.app.Fragment;
import androidx.lifecycle.ViewModelProvider;

import com.example.myapplication.ItemDetailActivity;
import com.example.myapplication.HistoryActivity;
import com.example.myapplication.R;
import com.example.myapplication.databinding.FragmentHomeBinding;

public class HomeFragment extends Fragment {

    private FragmentHomeBinding binding;

    public View onCreateView(@NonNull LayoutInflater inflater,
                             ViewGroup container, Bundle savedInstanceState) {
        HomeViewModel homeViewModel =
                new ViewModelProvider(this).get(HomeViewModel.class);



        binding = FragmentHomeBinding.inflate(inflater, container, false);
        View root = binding.getRoot();

        //final TextView textView = binding.textHome;
        //homeViewModel.getText().observe(getViewLifecycleOwner(), textView::setText);

        Button btnHistory = root.findViewById(R.id.btnHistory);
        btnHistory.setOnClickListener(v -> {
            Intent intent = new Intent(getActivity(), HistoryActivity.class);
            startActivity(intent);
        });
        Button btnDetail = root.findViewById(R.id.btnDetail);
        btnDetail.setOnClickListener(v -> {
            Intent intent = new Intent(getActivity(), ItemDetailActivity.class);
            startActivity(intent);
        });
       Button btnExit = root.findViewById(R.id.btnExit);
        btnExit.setOnClickListener(v -> {
            getActivity().finish();
        });

        return root;
    }

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}

 */