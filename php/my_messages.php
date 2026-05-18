<?php

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); 
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>رسائلي</title>
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #007bff; --sent-bg: #dcf8c6; --received-bg: #ffffff; }
        html, body {
            height: 100%; margin: 0; padding: 0; overflow: hidden;
            overscroll-behavior-y: contain; /* لمنع السحب للتحديث */
            font-family: 'Cairo', sans-serif; background-color: #f0f2f5;
        }
        .messages-page-container { display: flex; background-color: #fff; width: 100%; height: 100%; overflow: hidden; }
        .chat-list-sidebar { width: 350px; border-inline-end: 1px solid #ddd; display: flex; flex-direction: column; flex-shrink: 0; background-color: #f8f9fa; transition: margin-right 0.3s ease-in-out; }
        .chat-list-sidebar h2 { font-size: 1.5rem; margin: 0; padding: 20px; text-align: center; border-bottom: 1px solid #ddd; flex-shrink: 0; }
        #chatListContainer { overflow-y: auto; flex-grow: 1; overscroll-behavior-y: contain; }
        .chat-list-item { display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: background-color 0.2s; position: relative; }
        .chat-list-item:hover, .chat-list-item.active { background-color: #e9ecef; }
        .chat-list-item img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-inline-end: 15px; flex-shrink: 0; }
        .chat-info { flex-grow: 1; overflow: hidden; }
        .chat-info h4 { margin: 0 0 5px 0; font-size: 1rem; color: #333; }
        .chat-info p { margin: 0; font-size: 0.85rem; color: #777; }
        .chat-meta { display: flex; justify-content: space-between; align-items: center; margin-top: 5px; }
        .chat-meta .last-message-time { font-size: 0.75rem; color: #999; }
        .unread-count { background-color: #e60000; color: white; font-size: 11px; font-weight: bold; border-radius: 50%; padding: 2px 6px; }
        .chat-area { flex-grow: 1; display: flex; flex-direction: column; position: relative; }
        .chat-header { display: flex; align-items: center; padding: 10px 20px; border-bottom: 1px solid #ddd; background-color: #f0f0f0; flex-shrink: 0; z-index: 10; }
        .chat-header h3 { margin: 0; font-size: 1.2rem; }
        .chat-header span { font-size: 0.9rem; color: #777; }
        .messages-display {
            flex-grow: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px;
            background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png');
            background-color: #E5DDD5; overscroll-behavior-y: contain;
        }
        .message-bubble { max-width: 80%; padding: 8px 12px; border-radius: 12px; line-height: 1.4; word-wrap: break-word; box-shadow: 0 1px 1px rgba(0,0,0,0.1); position: relative; }
        .message-bubble.sent { background-color: var(--sent-bg); align-self: flex-end; }
        .message-bubble.received { background-color: var(--received-bg); align-self: flex-start; }
        .message-text { margin: 0; font-size: 0.95rem; }
        .message-footer { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 5px; }
        .message-time { font-size: 0.7rem; color: #888; }
        .placeholder-area { display: flex; justify-content: center; align-items: center; height: 100%; color: #777; flex-direction: column; text-align: center; padding: 20px; }
        .message-input-container { display: flex; padding: 10px; border-top: 1px solid #ddd; background-color: #f0f0f0; flex-shrink: 0; z-index: 10; }
        .message-input-container textarea { flex-grow: 1; border: 1px solid #ddd; border-radius: 20px; padding: 10px 15px; font-size: 1rem; resize: none; min-height: 40px; max-height: 100px; font-family: 'Cairo', sans-serif; }
        .message-input-container button { background-color: var(--primary-color); color: white; border: none; border-radius: 50%; width: 45px; height: 45px; cursor: pointer; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; margin-right: 10px; }
        .reply-btn { opacity: 0; visibility: hidden; cursor: pointer; font-size: 14px; color: #555; transition: opacity 0.2s; }
        .message-bubble:hover .reply-btn { opacity: 1; visibility: visible; }
        .quoted-message { background-color: rgba(0, 0, 0, 0.05); border-right: 3px solid var(--primary-color); padding: 8px; margin-bottom: 5px; border-radius: 4px; }
        .quoted-message p { margin: 0; font-size: 0.85em; }
        .quoted-message .sender { font-weight: bold; color: var(--primary-color); font-size: 0.8em; }
        .reply-preview-bar { display: none; padding: 10px 15px; background-color: #f8f9fa; border-top: 1px solid #ddd; font-size: 0.9em; position: relative; flex-shrink: 0; }
        .reply-preview-bar p { margin: 0; }
        .reply-preview-bar .close-reply { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 1.2rem; }
        .mobile-back-btn { display: none; }
        #scrollToBottomBtn {
            position: absolute; bottom: 80px; left: 20px; width: 40px; height: 40px; background-color: rgba(255, 255, 255, 0.9);
            color: #555; border-radius: 50%; border: 1px solid #ddd; display: none; align-items: center; justify-content: center;
            cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 100;
        }
        @media (max-width: 768px) {
            .messages-page-container { overflow-x: hidden; position: relative; }
            .chat-list-sidebar { width: 100%; position: absolute; top: 0; right: 0; height: 100%; z-index: 10; margin-right: 0; transition: margin-right 0.3s ease-in-out; }
            .chat-area { width: 100%; height: 100%; position: absolute; top: 0; right: 0; z-index: 5; margin-right: 100%; transition: margin-right 0.3s ease-in-out; }
            .messages-page-container.mobile-chat-view .chat-list-sidebar { margin-right: -100%; }
            .messages-page-container.mobile-chat-view .chat-area { margin-right: 0; }
            .mobile-back-btn { display: inline-block; margin-left: 15px; font-size: 1.2rem; cursor: pointer; }
            .reply-btn { opacity: 1; visibility: visible; }
        }
    </style>
</head>
<body>
    <div class="messages-page-container" id="messagesPageContainer">
        <div class="chat-list-sidebar">
            <h2>محادثاتي</h2>
            <div id="chatListContainer">
                <div class="placeholder-area">جاري تحميل المحادثات...</div>
            </div>
        </div>
        <div class="chat-area">
            <div class="chat-header">
                <i class="fas fa-arrow-right mobile-back-btn" id="mobileBackBtn"></i>
                <div>
                    <h3 id="currentChatAdTitle"></h3>
                    <span id="currentChatOtherUsername"></span>
                </div>
            </div>
            <div class="messages-display" id="messagesDisplay"></div>
            <button id="scrollToBottomBtn"><i class="fas fa-arrow-down"></i></button>
            <div class="reply-preview-bar" id="replyPreviewBar">
                <span class="close-reply" id="closeReplyPreview">&times;</span>
                <p>الرد على: <strong id="replyPreviewText"></strong></p>
            </div>
            <div class="message-input-container">
                <textarea id="messageInput" placeholder="اكتب رسالتك هنا..."></textarea>
                <button id="sendMessageBtn"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const currentLoggedInUserId = <?php echo json_encode($current_user_id); ?>;
        const container = document.getElementById('messagesPageContainer');
        const chatListContainer = document.getElementById('chatListContainer');
        const currentChatAdTitle = document.getElementById('currentChatAdTitle');
        const currentChatOtherUsername = document.getElementById('currentChatOtherUsername');
        const messagesDisplay = document.getElementById('messagesDisplay');
        const messageInput = document.getElementById('messageInput');
        const sendMessageBtn = document.getElementById('sendMessageBtn');
        const mobileBackBtn = document.getElementById('mobileBackBtn');
        const replyPreviewBar = document.getElementById('replyPreviewBar');
        const replyPreviewText = document.getElementById('replyPreviewText');
        const closeReplyPreview = document.getElementById('closeReplyPreview');
        const scrollToBottomBtn = document.getElementById('scrollToBottomBtn');

        let activeChat = { ad_id: null, other_user_id: null, element: null };
        let replyingTo = { messageId: null, text: null };
        let messagePollingInterval = null;
        
        let startY = 0;
        let isPulling = false;
        messagesDisplay.addEventListener('touchstart', (e) => {
            if (messagesDisplay.scrollTop === 0) {
                startY = e.touches[0].pageY;
                isPulling = true;
            }
        }, { passive: true });
        messagesDisplay.addEventListener('touchmove', (e) => {
            if (isPulling) {
                const currentY = e.touches[0].pageY;
                if (currentY > startY) {
                    e.preventDefault();
                } else {
                    isPulling = false;
                }
            }
        }, { passive: false });
        messagesDisplay.addEventListener('touchend', (e) => {
            isPulling = false;
        });

        if (messagesDisplay) {
            messagesDisplay.addEventListener('scroll', () => {
                const isScrolledUp = messagesDisplay.scrollHeight - messagesDisplay.scrollTop > messagesDisplay.clientHeight + 200;
                scrollToBottomBtn.style.display = isScrolledUp ? 'flex' : 'none';
            });
        }
        if (scrollToBottomBtn) {
            scrollToBottomBtn.addEventListener('click', () => {
                messagesDisplay.scrollTo({ top: messagesDisplay.scrollHeight, behavior: 'smooth' });
            });
        }

        async function fetchChatList() {
            try {
                const response = await fetch('get_chat_list.php');
                const data = await response.json();
                if (data.success) {
                    displayChatList(data.chats);
                } else {
                    chatListContainer.innerHTML = `<div class="placeholder-area">${data.message || 'فشل التحميل.'}</div>`;
                }
            } catch (error) {
                console.error('Fetch chat list error:', error);
                chatListContainer.innerHTML = `<div class="placeholder-area">خطأ في الاتصال.</div>`;
            }
        }

        function displayChatList(chats) {
            const currentActiveKey = activeChat.ad_id ? `${activeChat.ad_id}-${activeChat.other_user_id}` : null;
            chatListContainer.innerHTML = '';
            if (chats.length === 0) {
                chatListContainer.innerHTML = '<div class="placeholder-area">لا توجد محادثات.</div>';
                return;
            }
            chats.forEach(chat => {
                const chatItem = document.createElement('div');
                chatItem.classList.add('chat-list-item');
                if (`${chat.ad_id}-${chat.other_user_id}` === currentActiveKey) {
                    chatItem.classList.add('active');
                    activeChat.element = chatItem;
                }
                chatItem.dataset.adId = chat.ad_id;
                chatItem.dataset.otherUserId = chat.other_user_id;
                chatItem.dataset.adTitle = chat.ad_title;
                chatItem.dataset.otherUsername = chat.other_username;
                const time = chat.last_message_time ? new Date(chat.last_message_time).toLocaleTimeString('ar-SY', { hour: '2-digit', minute: '2-digit' }) : '';
                chatItem.innerHTML = `<img src="${chat.ad_image_url}" alt="صورة" onerror="this.src='https://via.placeholder.com/50';"><div class="chat-info"><h4>${chat.ad_title}</h4><p>${chat.last_message_text || '...'}</p><div class="chat-meta"><span class="last-message-time">${time}</span>${chat.unread_count > 0 ? `<span class="unread-count">${chat.unread_count}</span>` : ''}</div></div>`;
                chatItem.addEventListener('click', () => { openChat(chatItem.dataset, chatItem); });
                chatListContainer.appendChild(chatItem);
            });
        }

        function openChat(chatData, chatItemElement) {
            if (activeChat.element) activeChat.element.classList.remove('active');
            activeChat = { ad_id: parseInt(chatData.adId), other_user_id: parseInt(chatData.otherUserId), element: chatItemElement };
            chatItemElement.classList.add('active');
            currentChatAdTitle.textContent = chatData.adTitle;
            currentChatOtherUsername.textContent = `محادثة مع: ${chatData.otherUsername}`;
            messagesDisplay.innerHTML = '';
            messageInput.value = '';
            const unreadSpan = chatItemElement.querySelector('.unread-count');
            if (unreadSpan) unreadSpan.style.display = 'none';
            fetchMessages(true);
            startPolling();
            container.classList.add('mobile-chat-view');
        }

        async function fetchMessages(isFirstFetch = false) {
            if (!activeChat.ad_id) return;
            try {
                const response = await fetch(`fetch_messages.php?ad_id=${activeChat.ad_id}&owner_id=${activeChat.other_user_id}`);
                const messages = await response.json();
                displayMessages(messages, isFirstFetch);
            } catch (error) { console.error('Error fetching messages:', error); }
        }
        
        function displayMessages(messages, isFirstFetch) {
            const shouldScroll = isFirstFetch || (messagesDisplay.scrollHeight - messagesDisplay.clientHeight <= messagesDisplay.scrollTop + 50);
            messagesDisplay.innerHTML = '';
            if (!messages || messages.length === 0) {
                messagesDisplay.innerHTML = '<div class="placeholder-area"><p>ابدأ المحادثة!</p></div>';
                return;
            }
            messages.forEach(msg => {
                let quotedHtml = '';
                if (msg.reply_to_message_id && msg.replied_message_text) {
                    const senderName = msg.replied_sender_username || 'مستخدم';
                    quotedHtml = `<div class="quoted-message"><span class="sender">${senderName}</span><p>${msg.replied_message_text}</p></div>`;
                }
                const messageClass = msg.sender_id == currentLoggedInUserId ? 'sent' : 'received';
                const time = new Date(msg.created_at).toLocaleTimeString('ar-SY', { hour: '2-digit', minute: '2-digit' });
                const senderNameCurrent = msg.sender_id == currentLoggedInUserId ? 'أنت' : (msg.sender_username || 'الطرف الآخر');
                const messageHtml = `<div class="message-bubble ${messageClass}" data-message-id="${msg.id}">${quotedHtml}<p class="message-text">${msg.message}</p><div class="message-footer"><span style="font-weight:bold; font-size:0.8em;">${senderNameCurrent}</span><span class="message-time">${time}</span><i class="fas fa-reply reply-btn"></i></div></div>`;
                messagesDisplay.innerHTML += messageHtml;
            });
            if (shouldScroll) messagesDisplay.scrollTop = messagesDisplay.scrollHeight;
        }

        async function sendMessage() {
            const messageText = messageInput.value.trim();
            if (!messageText || !activeChat.ad_id) return;
            const formData = new FormData();
            formData.append('ad_id', activeChat.ad_id);
            formData.append('message', messageText);
            formData.append('receiver_id', activeChat.other_user_id);
            formData.append('reply_to_message_id', replyingTo.messageId || '');
            const tempMessageText = messageInput.value;
            messageInput.value = '';
            cancelReply();
            try {
                const response = await fetch('send_message.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    await fetchMessages(true);
                    await fetchChatList();
                } else {
                    alert('فشل إرسال الرسالة: ' + data.message);
                    messageInput.value = tempMessageText;
                }
            } catch (error) {
                alert('حدث خطأ في الاتصال.');
                messageInput.value = tempMessageText;
            }
        }

        function startReply(messageId, messageText) {
            replyingTo = { messageId, text: messageText };
            replyPreviewText.textContent = messageText;
            replyPreviewBar.style.display = 'block';
            messageInput.focus();
        }

        function cancelReply() {
            replyingTo = { messageId: null, text: null };
            replyPreviewBar.style.display = 'none';
        }
        
        function startPolling() {
            if (messagePollingInterval) clearInterval(messagePollingInterval);
            messagePollingInterval = setInterval(fetchMessages, 5000);
        }

        function stopPolling() {
            if (messagePollingInterval) {
                clearInterval(messagePollingInterval);
                messagePollingInterval = null;
            }
        }
        
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopPolling();
            } else {
                if (activeChat.ad_id) {
                    fetchMessages(true);
                    startPolling();
                }
            }
        });

        sendMessageBtn.addEventListener('click', sendMessage);
        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
        });
        if (mobileBackBtn) mobileBackBtn.addEventListener('click', () => {
            container.classList.remove('mobile-chat-view');
            if (activeChat.element) activeChat.element.classList.remove('active');
            activeChat = { ad_id: null, other_user_id: null, element: null };
            stopPolling();
        });
        if (closeReplyPreview) closeReplyPreview.addEventListener('click', cancelReply);

        messagesDisplay.addEventListener('click', (event) => {
            if (event.target.classList.contains('reply-btn')) {
                const bubble = event.target.closest('.message-bubble');
                const messageId = bubble.dataset.messageId;
                const messageText = bubble.querySelector('.message-text').textContent;
                startReply(messageId, messageText);
            }
        });
        
        fetchChatList();
    });
    </script>
</body>
</html>