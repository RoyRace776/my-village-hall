<?php
class MYVH_Settings_Manager {

    private $general;
    private $booking;
    private $email;

    public function general() {

        if (!$this->general) {
            $this->general = new MYVH_General_Settings();
        }

        return $this->general;
    }

    public function booking() {

        if (!$this->booking) {
            $this->booking = new MYVH_Booking_Settings();
        }

        return $this->booking;
    }

}