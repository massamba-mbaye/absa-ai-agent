document.getElementById('chatForm').addEventListener('submit', async function(event) {
    event.preventDefault();
    const userInput = document.getElementById('userInput');
    const chatbox = document.getElementById('chatbox');

    // Générer un ID pour le message (timestamp + random pour s'assurer de l'unicité)
    const messageId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);

    // Récupérer l'ID de conversation depuis un attribut data ou le générer
    let conversationId = chatbox.dataset.conversationId;
    if (!conversationId) {
        conversationId = 'conv_' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        chatbox.dataset.conversationId = conversationId;
    }

    // Afficher le message de l'utilisateur
    const userMessage = userInput.value.trim();
    if (userMessage === "") return;

    const userMessageElement = document.createElement('div');
    userMessageElement.className = 'message user';
    userMessageElement.dataset.messageId = messageId;
    userMessageElement.innerHTML = userMessage;
    chatbox.appendChild(userMessageElement);
    userInput.value = '';

    // Faire défiler le chatbox vers le bas
    chatbox.scrollTop = chatbox.scrollHeight;

    // Afficher l'indicateur de "Absa est en train d'écrire"
    const typingIndicator = document.createElement('div');
    typingIndicator.className = 'typing-indicator';

    // Ajouter l'icône
    const typingIcon = document.createElement('img');
    typingIcon.src = 'img/favicon.png';
    typingIcon.alt = 'Typing...';
    typingIcon.className = 'typing-icon';

    // Ajouter le texte
    const typingText = document.createElement('span');
    typingText.innerHTML = "Absa est en train d'écrire";

    // Conteneur des points
    const dotContainer = document.createElement('span');
    dotContainer.className = 'dot-container';
    dotContainer.innerHTML = '<span class="dot"></span><span class="dot"></span><span class="dot"></span>';

    // Ajouter l'icône et le texte au typingIndicator
    typingIndicator.appendChild(typingIcon);
    typingIndicator.appendChild(typingText);
    typingIndicator.appendChild(dotContainer);

    chatbox.appendChild(typingIndicator);
    chatbox.scrollTop = chatbox.scrollHeight;


    // Envoyer le message à request-treatment.php
    try {
        const response = await fetch('includes/request-treatment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ userMessage })
        });

        const data = await response.json();

        // Supprimer l'indicateur de "Absa est en train d'écrire..."
        chatbox.removeChild(typingIndicator);

        // Générer un ID pour la réponse
        const botMessageId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);

        // Afficher la réponse du chatbot formatée avec Marked.js
        const botMessageElement = document.createElement('div');
        botMessageElement.className = 'message bot';
        botMessageElement.dataset.messageId = botMessageId;
        if (data.success) {
            botMessageElement.innerHTML = marked.parse(data.response); // Convertir le Markdown en HTML
        } else {
            botMessageElement.innerHTML = `<b>Erreur:</b> ${data.error}`;
        }
        chatbox.appendChild(botMessageElement);

        // Faire défiler le chatbox vers le bas
        chatbox.scrollTop = chatbox.scrollHeight;

        // Détecter les liens dans la réponse du bot et les enregistrer
        if (data.success) {
            detectLinks(botMessageElement, conversationId, botMessageId);
        }

    } catch (error) {
        console.error("Erreur lors de la récupération de la réponse :", error);
        chatbox.removeChild(typingIndicator);
        const errorMessage = document.createElement('div');
        errorMessage.className = 'message bot error';
        errorMessage.innerHTML = "<b>Erreur :</b> Impossible de contacter le serveur.";
        chatbox.appendChild(errorMessage);
        chatbox.scrollTop = chatbox.scrollHeight;
    }
});

// Fonction pour détecter les liens dans un message
function detectLinks(messageElement, conversationId, messageId) {
    const links = messageElement.querySelectorAll('a');
    
    // Parcourir tous les liens dans le message
    links.forEach(link => {
        const href = link.getAttribute('href');
        
        // Enregistrer l'apparition du lien
        trackLinkEvent('link_detected', conversationId, messageId, href);
        
        // Ajouter un attribut de données pour le suivi
        link.dataset.tracked = 'false';
        
        // Ajouter un gestionnaire d'événements pour le clic
        link.addEventListener('click', function(e) {
            // Si le lien n'a pas encore été cliqué ou si nous voulons suivre tous les clics
            if (link.dataset.tracked === 'false') {
                trackLinkEvent('link_clicked', conversationId, messageId, href);
                link.dataset.tracked = 'true';
            }
        });
    });
}

// Fonction pour enregistrer un événement lié à un lien
async function trackLinkEvent(eventType, conversationId, messageId, link) {
    try {
        await fetch('includes/track_links.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                event_type: eventType,
                conversation_id: conversationId,
                message_id: messageId,
                link: link
            })
        });
    } catch (error) {
        console.error('Erreur lors du suivi du lien:', error);
    }
}

// Fonction pour démarrer une nouvelle conversation
function startNewConversation() {
    // Générer un nouvel ID de conversation
    const conversationId = 'conv_' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    document.getElementById('chatbox').dataset.conversationId = conversationId;
    
    // Envoyer une requête pour réinitialiser la session
    fetch('includes/reset-conversation.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Vider le chatbox
                document.getElementById('chatbox').innerHTML = '';
            }
        })
        .catch(error => console.error("Erreur lors de la réinitialisation :", error));
}

// Ajuste la position du formulaire lorsque le clavier est affiché
function adjustFormPosition() {
    const form = document.getElementById('chatForm');
    const keyboardHeight = window.innerHeight - document.documentElement.clientHeight;
    form.style.marginBottom = `${keyboardHeight}px`;
}

document.getElementById('userInput').addEventListener('focus', adjustFormPosition);
document.getElementById('userInput').addEventListener('blur', () => {
    document.getElementById('chatForm').style.marginBottom = '0';
});

// Ajuste la hauteur de l'écran pour les mobiles
function adjustHeight() {
    const vh = window.innerHeight * 0.01;
    document.documentElement.style.setProperty('--vh', `${vh}px`);
}

window.addEventListener('resize', adjustHeight);
adjustHeight();