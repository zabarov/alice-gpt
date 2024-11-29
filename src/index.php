<?php
// src/index.php

require 'vendor/autoload.php';

use Dotenv\Dotenv;
use GPT\GPTClient;

// Загрузка переменных окружения из файла .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Начало сессии для хранения состояния пользователей
session_start();

// Инициализация состояния пользователей, если еще не инициализировано
if (!isset($_SESSION['users_state'])) {
    $_SESSION['users_state'] = [];
}

/**
 * Функция для очистки приветствия Алисы из запроса пользователя.
 *
 * @param string $request Оригинальное высказывание пользователя.
 * @return string Очищенный запрос.
 */
function cleanRequest(string $request): string {
    $cutWords = ['Алиса', 'алиса'];
    foreach ($cutWords as $word) {
        if (mb_stripos($request, $word) === 0) {
            $request = mb_substr($request, mb_strlen($word));
            break; // Предполагается, что приветствие только одно в начале
        }
    }
    return trim($request);
}

// Инициализация GPTClient
try {
    $gptClient = new GPTClient();
} catch (Exception $e) {
    // Логирование ошибки
    error_log('Ошибка инициализации GPTClient: ' . $e->getMessage());

    // Формирование ответа Алисе с сообщением об ошибке
    $errorResponse = [
        'session' => $_POST['session'] ?? [],
        'version' => $_POST['version'] ?? '1.0',
        'response' => [
            'text' => 'Произошла ошибка при инициализации сервиса.',
            'tts' => 'Произошла ошибка при инициализации сервиса.',
            'end_session' => false
        ]
    ];

    header('Content-Type: application/json');
    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
    exit;
}

// Обработка POST-запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Чтение входного JSON
    $input = json_decode(file_get_contents('php://input'), true);

    // Проверка корректности JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Некорректный JSON: ' . json_last_error_msg());

        // Формирование ответа Алисе с сообщением об ошибке
        $errorResponse = [
            'session' => $input['session'] ?? [],
            'version' => $input['version'] ?? '1.0',
            'response' => [
                'text' => 'Произошла ошибка при обработке вашего запроса.',
                'tts' => 'Произошла ошибка при обработке вашего запроса.',
                'end_session' => false
            ]
        ];

        header('Content-Type: application/json');
        echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Инициализация ответа
    $response = [
        'session' => $input['session'],
        'version' => $input['version'],
        'response' => [
            'end_session' => false
        ]
    ];

    // Извлечение session_id для управления состоянием
    $sessionId = $input['session']['session_id'] ?? null;

    if ($sessionId) {
        // Инициализация состояния пользователя, если еще не существует
        if (!isset($_SESSION['users_state'][$sessionId])) {
            $_SESSION['users_state'][$sessionId] = [
                'messages' => []
            ];
        }

        // Ссылка на состояние текущего пользователя
        $userState = &$_SESSION['users_state'][$sessionId];
    } else {
        // Если session_id отсутствует, невозможно управлять состоянием
        error_log('Отсутствует session_id в запросе.');

        $response['response']['text'] = 'Не удалось определить сессию. Попробуйте еще раз.';
        $response['response']['tts'] = 'Не удалось определить сессию. Попробуйте еще раз.';
        header('Content-Type: application/json');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Проверка наличия оригинального высказывания пользователя
    if (!empty($input['request']['original_utterance'])) {
        // Очистка запроса от приветствия Алисы
        $userMessage = cleanRequest($input['request']['original_utterance']);

        // Добавление очищенного сообщения в историю
        $userState['messages'][] = $userMessage;

        // Получение ответа от GPT
        $botReply = $gptClient->ask($userMessage, $userState['messages']);

        // Формирование текста и TTS ответа
        $response['response']['text'] = $botReply;
        $response['response']['tts'] = $botReply . '<speaker audio="alice-sounds-things-door-2.opus">';

        // (Опционально) Вы можете добавить логику завершения сессии
        // Например, если пользователь сказал "до свидания"
        // if (stripos($userMessage, 'до свидания') !== false) {
        //     $response['response']['end_session'] = true;
        // }
    } else {
        // Если оригинальное высказывание отсутствует
        $response['response']['text'] = 'Я умный чат-бот. Спроси что-нибудь.';
        $response['response']['tts'] = 'Я умный чат-бот. Спроси что-нибудь.';
    }

    // Установка заголовка Content-Type как JSON
    header('Content-Type: application/json');

    // Отправка ответа
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} else {
    // Если метод запроса не POST, возвращаем ошибку 405
    header("HTTP/1.1 405 Method Not Allowed");
    echo "Метод не поддерживается.";
}