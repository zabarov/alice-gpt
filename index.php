<?php
require 'vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;

// Загрузка переменных окружения
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Инициализация клиента OpenAI
$client = new Client([
    'base_uri' => 'https://api.openai.com',
    'timeout'  => 30.0,
]);

// Хранение состояния пользователей (можно заменить на базу данных)
session_start();
if (!isset($_SESSION['users_state'])) {
    $_SESSION['users_state'] = [];
}

// Функция для очистки приветствия Алисы
function cleanRequest($request) {
    $cutWords = ['Алиса', 'алиса'];
    foreach ($cutWords as $word) {
        if (mb_stripos($request, $word) === 0) {
            $request = mb_substr($request, mb_strlen($word));
        }
    }
    return trim($request);
}

// Функция для взаимодействия с OpenAI
function askOpenAI($message, $messages, $client) {
    $apiKey = $_ENV['OPENAI_API_KEY'];
    $allMessages = $messages;
    $allMessages[] = $message;

    $formattedMessages = [];
    foreach ($allMessages as $msg) {
        $formattedMessages[] = [
            "role" => "user",
            "content" => $msg
        ];
    }

    try {
        $response = $client->post('/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-4o',
                'messages' => $formattedMessages
            ],
        ]);

        $body = json_decode($response->getBody(), true);
        return trim($body['choices'][0]['message']['content']);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return 'Не удалось получить ответ от сервиса.';
    }
}

// Обработка POST-запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $response = [
        'session' => $input['session'],
        'version' => $input['version'],
        'response' => [
            'end_session' => false
        ]
    ];

    $sessionId = $input['session']['session_id'];
    if (!isset($_SESSION['users_state'][$sessionId])) {
        $_SESSION['users_state'][$sessionId] = [
            'messages' => []
        ];
    }

    $userState = &$_SESSION['users_state'][$sessionId];

    if (!empty($input['request']['original_utterance'])) {
        $userMessage = cleanRequest($input['request']['original_utterance']);
        $userState['messages'][] = $userMessage;

        $botReply = askOpenAI($userMessage, $userState['messages'], $client);
        $response['response']['text'] = $botReply;
        $response['response']['tts'] = $botReply . '<speaker audio="alice-sounds-things-door-2.opus">';
    } else {
        $response['response']['text'] = 'Я умный чат-бот. Спроси что-нибудь.';
        $response['response']['tts'] = 'Я умный чат-бот. Спроси что-нибудь.';
    }

    header('Content-Type: application/json');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} else {
    // Обработка других типов запросов, если необходимо
    header("HTTP/1.1 405 Method Not Allowed");
    echo "Метод не поддерживается.";
}