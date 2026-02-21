# 🤖 Chatbot GPT-4 — Azure AI Foundry

Exemple de chatbot web en PHP utilisant **Azure OpenAI GPT-4** via **Azure AI Foundry**.

## 📋 Prérequis

- PHP 8.0+ avec extension `curl`
- Un compte Azure avec un déploiement GPT-4 sur **Azure AI Foundry**

## ⚙️ Configuration

### 1. Déployer un modèle GPT-4

1. Ouvre [AI Foundry](https://ai.azure.com)
2. Crée un hub → un projet
3. Va dans **Déploiements** → déploie `gpt-4`
4. Note l'**endpoint** et la **clé API**

### 2. Configurer l'application

Édite `config.php` avec tes valeurs :

```php
define('AZURE_ENDPOINT',   'https://YOUR_RESOURCE.openai.azure.com');
define('AZURE_API_KEY',    'YOUR_API_KEY');
define('AZURE_DEPLOYMENT', 'gpt-4');
```

Ou utilise des variables d'environnement (recommandé en production) :

```bash
export AZURE_OPENAI_ENDPOINT="https://YOUR_RESOURCE.openai.azure.com"
export AZURE_OPENAI_API_KEY="YOUR_API_KEY"
export AZURE_OPENAI_DEPLOYMENT="gpt-4"
```

### 3. Lancer en local

```bash
php -S localhost:8080
```

Puis ouvre http://localhost:8080

## 📁 Structure

```
├── index.php     # Interface utilisateur (HTML/CSS/JS)
├── api.php       # Backend — appel à Azure OpenAI
├── config.php    # Configuration (endpoint, clé, deployment)
└── .gitignore
```

## 🔒 Sécurité

- Ne jamais committer `config.php` avec de vraies clés
- Utiliser des variables d'environnement en production
- Ajouter une authentification si exposé publiquement
- Configurer un **Content Filter** dans Azure AI Foundry

## 🚀 Déploiement

Compatible avec tout hébergeur PHP (Azure App Service, Apache, Nginx+PHP-FPM).

Pour Azure App Service, configure les variables dans **Configuration > Paramètres d'application**.
