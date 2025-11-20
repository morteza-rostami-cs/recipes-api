<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Email_Service {
  
  public static function send_email( $to, $subject, $message ) {
    $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

    $sent = wp_mail( $to, $subject, $message, $headers );

    if ( ! $sent ) {
      error_log( 'Email failed to send to: ' . $to );
      return false;
    }

    return true;
  }

  public static function send_otp( $email, $otp_code ) {
    $subject = 'Your Recipe App OTP Code';
    $message = "
      <h2>Your One-Time Password (OTP)</h2>
      <p>Use this code to verify your account:</p>
      <h1 style='color:#2a9d8f;'>$otp_code</h1>
      <p>This code will expire in 5 minutes.</p>
    ";
    return self::send_email( $email, $subject, $message );
  }
}
