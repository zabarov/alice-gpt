<?php

namespace GPT;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class GPTClient
{
    /**
     * @var Client Guzzle HTTP клиент для отправки запросов к OpenAI API.
     */
    private $client;

    /**
     * @var string Ваш API ключ OpenAI.
     */
    private $apiKey;

    /**
     * Конструктор класса GPTClient.
     *
     * Инициализирует Guzzle HTTP клиента и загружает API ключ из переменных окружения.
     *
     * @throws \Exception Если API ключ не установлен.
     */
    public function __construct()
    {
        $this->apiKey = getenv('OPENAI_API_KEY');
        if (!$this->apiKey) {
            throw new \Exception('OPENAI_API_KEY не установлен в переменных окружения.');
        }

        $this->client = new Client([
            'base_uri' => 'https://api.openai.com',
            'timeout'  => 30.0, // Таймаут запроса в секундах
        ]);
    }

    /**
     * Отправляет запрос к OpenAI API и получает ответ от ChatGPT.
     *
     * @param string $message Текущее сообщение пользователя.
     * @param array $previousMessages Массив предыдущих сообщений в диалоге.
     *
     * @return string Ответ от ChatGPT или сообщение об ошибке.
     */
    public function ask(string $message, array $previousMessages = []): string
    {
        // Объединяем историю сообщений с новым сообщением
        $allMessages = $previousMessages;
        $allMessages[] = $message;

        // Форматируем сообщения в структуру, ожидаемую OpenAI API
        $formattedMessages = array_map(function($msg) {
            return [
                'role' => 'user',
                'content' => $msg
            ];
        }, $allMessages);

        try {
            // Отправляем POST запрос к OpenAI API
            $response = $this->client->post('/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => $formattedMessages
                ],
            ]);

            // Декодируем JSON ответ
            $body = json_decode($response->getBody(), true);

            // Проверяем наличие ответа
            if (isset($body['choices'][0]['message']['content'])) {
                return trim($body['choices'][0]['message']['content']);
            } else {
                // Если структура ответа не соответствует ожиданиям
                error_log('Неверная структура ответа от OpenAI API: ' . $response->getBody());
                return 'Не удалось получить ответ от сервиса.';
            }
        } catch (RequestException $e) {
            // Логируем ошибку для отладки
            error_log('Ошибка при обращении к OpenAI API: ' . $e->getMessage());

            // Возвращаем сообщение об ошибке пользователю
            return 'Не удалось получить ответ от сервиса. Пожалуйста, попробуйте позже.';
        } catch (\Exception $e) {
            // Логируем любые другие исключения
            error_log('Общая ошибка: ' . $e->getMessage());
            return 'Произошла ошибка при обработке вашего запроса.';
        }
    }
}