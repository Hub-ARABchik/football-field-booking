<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiResponse;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Validator;
use App\Repositories\BookingRepository;
use PDOException;
use RuntimeException;

final class BookingController
{
    public function index(): void
    {
        $user = Auth::requireRole(['CUSTOMER']);
        ApiResponse::success((new BookingRepository())->customerBookings((int) $user['id']));
    }

    public function store(): void
    {
        $user = Auth::requireRole(['CUSTOMER']);
        Csrf::verify();
        $data = Request::data();
        $validator = (new Validator($data))
            ->required('field_id', 'Майдонча')->integer('field_id', 1, PHP_INT_MAX, 'Майдонча')
            ->required('booking_date', 'Сана')->date('booking_date', 'Сана')
            ->required('start_hour', 'Соат')->integer('start_hour', 0, 23, 'Соат')
            ->required('duration_hours', 'Давомнокӣ')->integer('duration_hours', 1, 6, 'Давомнокӣ');
        if ($validator->fails()) ApiResponse::error('validation_failed', 'The booking request is invalid.', 422, $validator->errors());
        $paymentMethod = (string) ($data['payment_method'] ?? 'cash');
        if (!in_array($paymentMethod, ['cash', 'transfer'], true)) ApiResponse::error('validation_failed', 'payment_method must be cash or transfer.', 422);
        try {
            $booking = (new BookingRepository())->create((int) $user['id'], (int) $data['field_id'], (string) $data['booking_date'], (int) $data['start_hour'], (int) $data['duration_hours'], $paymentMethod);
            ApiResponse::success($booking, 201);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                ApiResponse::error('slot_conflict', 'The selected time has already been booked.', 409);
            }
            throw $exception;
        } catch (RuntimeException $exception) {
            $map = [
                'field_not_available' => [404, 'field_not_available', 'This field is not currently available.'],
                'invalid_date' => [422, 'invalid_date', 'The booking date is invalid.'],
                'date_out_of_range' => [422, 'date_out_of_range', 'Bookings are allowed from today up to the configured advance limit.'],
                'time_in_past' => [422, 'time_in_past', 'The selected start time has already passed.'],
                'invalid_time_range' => [422, 'invalid_time_range', 'The selected duration or time is invalid.'],
                'outside_working_hours' => [422, 'outside_working_hours', 'The selected time is outside the field schedule.'],
                'slot_conflict' => [409, 'slot_conflict', 'The selected time has already been booked.'],
                'price_not_configured' => [409, 'price_not_configured', 'The field owner has not configured a price for this time.'],
            ];
            $item = $map[$exception->getMessage()] ?? [409, 'booking_failed', 'The booking could not be created.'];
            ApiResponse::error($item[1], $item[2], $item[0]);
        }
    }

    public function cancel(int $id): void
    {
        $user = Auth::requireRole(['CUSTOMER']);
        Csrf::verify();
        try {
            (new BookingRepository())->cancelForCustomer($id, (int) $user['id']);
            ApiResponse::success(['id' => $id, 'status' => 'cancelled']);
        } catch (RuntimeException $exception) {
            $map = [
                'booking_not_found' => [404, 'booking_not_found', 'The booking was not found.'],
                'booking_not_cancellable' => [409, 'booking_not_cancellable', 'This booking can no longer be cancelled.'],
                'cancellation_window_passed' => [409, 'cancellation_window_passed', 'Cancellation is not allowed within the configured cutoff.'],
            ];
            $item = $map[$exception->getMessage()] ?? [409, 'cancellation_failed', 'The booking could not be cancelled.'];
            ApiResponse::error($item[1], $item[2], $item[0]);
        }
    }

    public function ownerIndex(): void
    {
        $owner = Auth::requireRole(['OWNER']);
        ApiResponse::success((new BookingRepository())->ownerBookings((int) $owner['id']));
    }

    public function confirm(int $id): void
    {
        $owner = Auth::requireRole(['OWNER']);
        Csrf::verify();
        try {
            (new BookingRepository())->confirmByOwner($id, (int) $owner['id']);
            ApiResponse::success(['id' => $id, 'status' => 'confirmed']);
        } catch (RuntimeException) {
            ApiResponse::error('booking_not_confirmable', 'The booking was not found for this owner or is no longer pending.', 409);
        }
    }
}
