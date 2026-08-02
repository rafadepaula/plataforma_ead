import CsvImporter from './modules/CsvImporter';
import HttpClient from './modules/HttpClient';
import LessonPlayer from './modules/LessonPlayer';
import ModalManager from './modules/ModalManager';
import ModuleReorder from './modules/ModuleReorder';
import NotificationService from './modules/NotificationService';
import SmartInvitationForm from './modules/SmartInvitationForm';

window.HttpClient = HttpClient;
window.ModalManager = ModalManager;
window.NotificationService = NotificationService;
window.CsvImporter = new CsvImporter(HttpClient);
window.ModuleReorder = new ModuleReorder(HttpClient, NotificationService);
window.SmartInvitationForm = new SmartInvitationForm(HttpClient, NotificationService);
window.LessonPlayer = new LessonPlayer(HttpClient, NotificationService);

document.addEventListener('DOMContentLoaded', () => {
    // JavaScript modules auto-bind DOM handlers upon load
    window.CsvImporter.init();
    window.ModuleReorder.init();
    window.SmartInvitationForm.init();
    window.LessonPlayer.init();
});
