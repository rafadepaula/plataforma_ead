import HttpClient from './modules/HttpClient';
import ModalManager from './modules/ModalManager';
import NotificationService from './modules/NotificationService';

window.HttpClient = HttpClient;
window.ModalManager = ModalManager;
window.NotificationService = NotificationService;

document.addEventListener('DOMContentLoaded', () => {
    // JavaScript modules auto-bind DOM handlers upon load
});
