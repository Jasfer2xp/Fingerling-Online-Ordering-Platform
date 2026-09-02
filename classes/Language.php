<?php

/**
 * Language class for handling translations
 */

class Language {
    private $translations = [];
    private $currentLanguage = 'en';
    
    public function __construct($language = 'en') {
        $this->currentLanguage = $language;
        $this->loadTranslations($language);
    }
    
    /**
     * Load translations for the specified language
     */
    private function loadTranslations($language) {
        $translationFile = __DIR__ . '/../languages/' . ($language === 'tl' ? 'tagalog.php' : 'english.php');
        
        if (file_exists($translationFile)) {
            $this->translations = include $translationFile;
        } else {
            $this->translations = [];
        }
    }
    
    /**
     * Get translated text for a key
     */
    public function get($key, $default = null) {
        if (isset($this->translations[$key])) {
            return $this->translations[$key];
        }
        
        // Return default value or the key itself if translation not found
        return $default ?? $key;
    }
    
    /**
     * Translate text (alias for get)
     */
    public function translate($key, $default = null) {
        return $this->get($key, $default);
    }
    
    /**
     * Get current language
     */
    public function getCurrentLanguage() {
        return $this->currentLanguage;
    }
    
    /**
     * Set current language
     */
    public function setLanguage($language) {
        $this->currentLanguage = $language;
        $this->loadTranslations($language);
    }
    
    /**
     * Check if a translation exists for a key
     */
    public function has($key) {
        return isset($this->translations[$key]);
    }
}