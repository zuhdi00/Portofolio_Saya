package com.example.myapplication.ui.home;

import androidx.lifecycle.LiveData;
import androidx.lifecycle.MutableLiveData;
import androidx.lifecycle.ViewModel;

public class HomeViewModel extends ViewModel {
    // Untuk teks halaman beranda
    private final MutableLiveData<String> mText = new MutableLiveData<>();

    // Untuk navigasi
    private final MutableLiveData<Event<NavigationEvent>> navigationEvent = new MutableLiveData<>();

    public HomeViewModel() {
        mText.setValue("Tampilan Dari Halaman Beranda");
    }

    // Getter untuk teks
    public LiveData<String> getText() {
        return mText;
    }

    // Getter untuk event navigasi
    public LiveData<Event<NavigationEvent>> getNavigationEvent() {
        return navigationEvent;
    }

    // Handler untuk tombol history
    public void onHistoryClicked() {
        navigationEvent.setValue(new Event<>(new NavigationEvent(Destination.HISTORY)));
    }

    // Handler untuk tombol detail
    public void onDetailClicked() {
        navigationEvent.setValue(new Event<>(new NavigationEvent(Destination.ITEM_DETAIL)));
    }

    // Membersihkan event setelah di-handle
    public void onNavigationComplete() {
        navigationEvent.setValue(null);
    }
    public void onExitClicked() {
        // Misalnya keluar aplikasi atau trigger ke fragment

    }


    // Enum untuk tujuan navigasi
    public enum Destination {
        HISTORY,
        ITEM_DETAIL
    }


    // Class wrapper untuk event navigasi
    public static class NavigationEvent {
        private final Destination destination;

        public NavigationEvent(Destination destination) {
            this.destination = destination;
        }

        public Destination getDestination() {
            return destination;
        }
    }

    // Class untuk single event handling
    public static class Event<T> {
        private final T content;
        private boolean hasBeenHandled = false;

        public Event(T content) {
            this.content = content;
        }

        public T getContentIfNotHandled() {
            if (hasBeenHandled) {
                return null;
            }


            hasBeenHandled = true;
            return content;
        }
    }
}