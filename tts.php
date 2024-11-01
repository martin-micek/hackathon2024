<?php
// ElevenLabs Text-to-Speech API Integration
class ElevenLabsTTSConfig {
    // IMPORTANT: Replace with your actual ElevenLabs API key
    const API_KEY = 'sk_3b866cffe674c37dbd21e0be192e6bb84ba351f94da3ce36';
    const BASE_URL = 'https://api.elevenlabs.io/v1';
}

class ElevenLabsTextToSpeechService {
    private $apiKey;
    private $baseUrl;

    public function __construct($apiKey, $baseUrl) {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Get available voices from ElevenLabs
     * 
     * @return array List of available voices
     */
    public function getVoices() {
        $url = $this->baseUrl . '/voices';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'xi-api-key: ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Failed to fetch voices. HTTP Code: $httpCode");
        }

        return json_decode($response, true);
    }

    /**
     * Convert text to speech using ElevenLabs API
     * 
     * @param string $text Text to convert
     * @param string $voiceId Voice ID to use
     * @param array $settings Optional voice settings
     * @return string Path to generated audio file
     */
    public function convertTextToSpeech($text, $voiceId = null, $settings = []) {
        // Validate input
        if (empty($text)) {
            throw new Exception('Text cannot be empty');
        }

        // Use default voice if not specified
        if ($voiceId === null) {
            $voiceId = $this->getDefaultVoiceId();
        }

        // Prepare request payload
        $payload = [
            'text' => $text,
            'model_id' => $settings['model_id'] ?? 'eleven_monolingual_v1',
            'voice_settings' => [
                'stability' => $settings['stability'] ?? 0.5,
                'similarity_boost' => $settings['similarity_boost'] ?? 0.5
            ]
        ];

        // Construct API URL
        $url = $this->baseUrl . "/text-to-speech/{$voiceId}";

        // Initialize cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'xi-api-key: ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);

        // Execute request
        $audioContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Check response
        if ($httpCode !== 200) {
            throw new Exception("Text-to-Speech conversion failed. HTTP Code: $httpCode");
        }

        // Save audio file
        return $this->saveAudioFile($audioContent, "elevenlabs_tts_" . uniqid() . ".mp3");
    }

    /**
     * Save audio content to a file
     * 
     * @param string $audioContent Raw audio data
     * @param string $filename Output filename
     * @return string Full path to saved file
     */
    private function saveAudioFile($audioContent, $filename) {
        // Ensure save directory exists
        $saveDirectory = __DIR__ . '/audio_output/';
        if (!is_dir($saveDirectory)) {
            mkdir($saveDirectory, 0755, true);
        }

        // Full path for saving
        $fullPath = $saveDirectory . $filename;

        // Save audio file
        if (file_put_contents($fullPath, $audioContent) === false) {
            throw new Exception('Could not save audio file');
        }

        return $fullPath;
    }

    /**
     * Get default voice ID
     * 
     * @return string Default voice ID
     */
    private function getDefaultVoiceId() {
        // Fetch available voices
        $voices = $this->getVoices();

        // Return first voice ID if available
        if (!empty($voices['voices'])) {
            return $voices['voices'][0]['voice_id'];
        }

        // Fallback voice ID (you might want to replace this)
        return 'twenty_one';
    }
}

// Handle API request
function handleTextToSpeechRequest() {
    // Set CORS headers
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, xi-api-key');

    // Handle OPTIONS request for CORS preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    // Ensure it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        exit();
    }

    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    if (!isset($input['text'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No text provided']);
        exit();
    }

    try {
        // Initialize ElevenLabs TTS Service
        $ttsService = new ElevenLabsTextToSpeechService(
            ElevenLabsTTSConfig::API_KEY, 
            ElevenLabsTTSConfig::BASE_URL
        );

        // Optional: Allow client to specify voice and settings
        $voiceId = $input['voiceId'] ?? null;
        $settings = $input['settings'] ?? [];

        // Convert text to speech
        $audioPath = $ttsService->convertTextToSpeech(
            $input['text'], 
            $voiceId, 
            $settings
        );

        // Respond with audio file details
        echo json_encode([
            'success' => true,
            'audioPath' => str_replace(__DIR__, '', $audioPath),
            'filename' => basename($audioPath)
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Text-to-Speech conversion failed',
            'details' => $e->getMessage()
        ]);
    }
}

// Execute the request handler
handleTextToSpeechRequest();
?>
