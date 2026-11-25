<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiResponse;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Validator;
use App\Repositories\FieldRepository;

final class FieldController
{
    public function index(): void
    {
        $query = Request::query();
        $filters = [
            'q' => trim((string) ($query['q'] ?? '')),
            'city' => trim((string) ($query['city'] ?? '')),
            'size' => trim((string) ($query['size'] ?? '')),
            'surface' => trim((string) ($query['surface'] ?? '')),
            'price_min' => (string) ($query['price_min'] ?? ''),
            'price_max' => (string) ($query['price_max'] ?? ''),
            'sort' => (string) ($query['sort'] ?? 'recommended'),
        ];
        $queryLength = preg_match_all('/./us', $filters['q']);
        $cityLength = preg_match_all('/./us', $filters['city']);
        if (($queryLength === false ? strlen($filters['q']) : $queryLength) > 100 || ($cityLength === false ? strlen($filters['city']) : $cityLength) > 100) {
            ApiResponse::error('invalid_filter', 'Search filters are too long.', 422);
        }
        foreach (['price_min', 'price_max'] as $key) {
            if ($filters[$key] !== '' && (!is_numeric($filters[$key]) || (float) $filters[$key] < 0)) {
                ApiResponse::error('invalid_filter', 'Prices must be non-negative numbers.', 422);
            }
        }
        if ($filters['price_min'] !== '' && $filters['price_max'] !== '' && (float) $filters['price_min'] > (float) $filters['price_max']) {
            ApiResponse::error('invalid_filter', 'Minimum price cannot exceed maximum price.', 422);
        }
        $page = max(1, min(10000, (int) ($query['page'] ?? 1)));
        $perPage = max(1, min(48, (int) ($query['per_page'] ?? 12)));
        $result = (new FieldRepository())->paginatePublic($filters, $page, $perPage);
        ApiResponse::success($result['items'], 200, ['page' => $result['page'], 'per_page' => $result['per_page'], 'total' => $result['total'], 'pages' => $result['pages']]);
    }

    public function show(int $id): void
    {
        $field = (new FieldRepository())->findPublic($id);
        if (!$field) ApiResponse::error('field_not_found', 'The requested field was not found.', 404);
        ApiResponse::success($field);
    }

    public function availability(int $id): void
    {
        $week = filter_var($_GET['week'] ?? 0, FILTER_VALIDATE_INT);
        if ($week === false || $week < 0 || $week > 8) ApiResponse::error('invalid_week', 'Week offset must be between 0 and 8.', 422);
        $result = (new FieldRepository())->availability($id, $week);
        if ($result === []) ApiResponse::error('field_not_found', 'The requested field was not found.', 404);
        ApiResponse::success($result);
    }

    public function ownerIndex(): void
    {
        $user = Auth::requireRole(['OWNER']);
        ApiResponse::success((new FieldRepository())->ownerFields((int) $user['id']));
    }

    public function store(): void
    {
        $user = Auth::requireRole(['OWNER']);
        Csrf::verify();
        $data = Request::data();
        $validator = (new Validator($data))
            ->required('name', 'Номи майдонча')->string('name', 3, 100, 'Номи майдонча')
            ->required('city', 'Шаҳр')->string('city', 2, 100, 'Шаҳр')
            ->required('size', 'Андоза')->oneOf('size', ['5x5', '7x7', '11x11'], 'Андоза')
            ->required('surface', 'Рӯйпӯш')->oneOf('surface', ['Алафи сунъӣ', 'Алафи табиӣ', 'Паркет', 'Резина'], 'Рӯйпӯш')
            ->required('open_time', 'Вақти кушодан')->time('open_time', 'Вақти кушодан')
            ->required('close_time', 'Вақти бастан')->time('close_time', 'Вақти бастан')
            ->required('evening_from', 'Оғози нархи шом')->time('evening_from', 'Оғози нархи шом')
            ->required('capacity', 'Ғунҷоиш')->integer('capacity', 2, 100, 'Ғунҷоиш')
            ->string('address', 0, 255, 'Суроға')->string('description', 0, 4000, 'Тавсиф');
        if ($validator->fails()) ApiResponse::error('validation_failed', 'The submitted field data is invalid.', 422, $validator->errors());
        if (substr((string) $data['open_time'], 0, 5) >= substr((string) $data['close_time'], 0, 5)) {
            ApiResponse::error('validation_failed', 'The closing time must be later than the opening time.', 422, ['close_time' => 'Вақти бастан бояд аз вақти кушодан дертар бошад.']);
        }
        if (substr((string) $data['evening_from'], 0, 5) < substr((string) $data['open_time'], 0, 5) || substr((string) $data['evening_from'], 0, 5) >= substr((string) $data['close_time'], 0, 5)) {
            ApiResponse::error('validation_failed', 'Evening rate time must be inside the working hours.', 422, ['evening_from' => 'Оғози нархи шом бояд дар дохили вақти корӣ бошад.']);
        }
        $prices = $data['prices'] ?? null;
        $priceErrors = [];
        foreach (['weekday', 'weekend'] as $dayType) {
            foreach (['day', 'evening'] as $timeOfDay) {
                $value = is_array($prices) ? ($prices[$dayType][$timeOfDay] ?? null) : null;
                if (!is_numeric($value) || (float) $value <= 0 || (float) $value > 100000) {
                    $priceErrors["prices.{$dayType}.{$timeOfDay}"] = 'Нарх бояд аз 0 то 100000 сомонӣ бошад.';
                }
            }
        }
        if ($priceErrors !== []) ApiResponse::error('validation_failed', 'Configure all four hourly rates.', 422, $priceErrors);

        $id = (new FieldRepository())->createForOwner((int) $user['id'], [
            'prices' => $prices,
            'name' => trim((string) $data['name']), 'city' => trim((string) $data['city']),
            'address' => trim((string) ($data['address'] ?? '')), 'size' => $data['size'], 'surface' => $data['surface'],
            'description' => trim((string) ($data['description'] ?? '')), 'open_time' => $data['open_time'],
            'close_time' => $data['close_time'], 'evening_from' => $data['evening_from'], 'capacity' => (int) $data['capacity'],
        ]);
        ApiResponse::success(['id' => $id, 'status' => 'pending'], 201);
    }

    public function moderate(int $id): void
    {
        $admin = Auth::requireRole(['ADMIN']);
        Csrf::verify();
        $data = Request::data();
        $status = (string) ($data['status'] ?? '');
        if (!in_array($status, ['approved', 'rejected', 'suspended'], true)) {
            ApiResponse::error('validation_failed', 'Choose approved, rejected or suspended.', 422);
        }
        $pdo = \App\Core\Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE fields SET status = :status, is_active = :is_active, updated_at = NOW() WHERE id = :id');
            $stmt->execute(['status' => $status, 'is_active' => $status === 'approved' ? 1 : 0, 'id' => $id]);
            if ($stmt->rowCount() !== 1) {
                $exists = $pdo->prepare('SELECT id FROM fields WHERE id = :id');
                $exists->execute(['id' => $id]);
                if (!$exists->fetchColumn()) {
                    $pdo->rollBack();
                    ApiResponse::error('field_not_found', 'The field was not found.', 404);
                }
            }
            $audit = $pdo->prepare("INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, details, created_at) VALUES (:actor, 'field.moderated', 'field', :entity_id, :details, NOW())");
            $audit->execute(['actor' => $admin['id'], 'entity_id' => $id, 'details' => json_encode(['status' => $status], JSON_UNESCAPED_UNICODE)]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        ApiResponse::success(['id' => $id, 'status' => $status]);
    }
}
