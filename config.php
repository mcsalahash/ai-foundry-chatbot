<?php
/**
 * config.php — Configuration Azure OpenAI / AI Foundry
 * 
 * ⚠️ Ne jamais committer ce fichier avec de vraies valeurs.
 *    Utilise les variables d'environnement en production.
 */

// Azure OpenAI — à récupérer dans Azure AI Foundry > Déploiements
define('AZURE_ENDPOINT',    getenv('AZURE_OPENAI_ENDPOINT')    ?: 'https://YOUR_RESOURCE.openai.azure.com');
define('AZURE_API_KEY',     getenv('AZURE_OPENAI_API_KEY')     ?: 'YOUR_API_KEY');
define('AZURE_DEPLOYMENT',  getenv('AZURE_OPENAI_DEPLOYMENT')  ?: 'gpt-4');
define('AZURE_API_VERSION', getenv('AZURE_OPENAI_API_VERSION') ?: '2024-02-01');

// Personnalité du chatbot
define('SYSTEM_PROMPT', 'Tu es un assistant IA professionnel et utile. ' .
    'Tu réponds de manière claire, concise et structurée. ' .
    'Si tu ne sais pas quelque chose, tu le dis honnêtement.');
