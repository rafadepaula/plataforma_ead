import AuditLogDiffModal from './modules/AuditLogDiffModal';
import CsvImporter from './modules/CsvImporter';
import ForumEditHistory from './modules/ForumEditHistory';
import ForumPolling from './modules/ForumPolling';
import ForumReportModal from './modules/ForumReportModal';
import HttpClient from './modules/HttpClient';
import LessonPlayer from './modules/LessonPlayer';
import ModalManager from './modules/ModalManager';
import ModuleReorder from './modules/ModuleReorder';
import NotificationBell from './modules/NotificationBell';
import NotificationService from './modules/NotificationService';
import SmartInvitationForm from './modules/SmartInvitationForm';
import QuizBuilder from './quiz-builder';
import QuizTimer from './quiz-timer';

window.HttpClient = HttpClient;
window.ModalManager = ModalManager;
window.NotificationService = NotificationService;
window.CsvImporter = new CsvImporter(HttpClient);
window.ModuleReorder = new ModuleReorder(HttpClient, NotificationService);
window.SmartInvitationForm = new SmartInvitationForm(HttpClient, NotificationService);
window.LessonPlayer = new LessonPlayer(HttpClient, NotificationService);
window.QuizBuilder = new QuizBuilder(NotificationService);
window.QuizTimer = new QuizTimer();
window.ForumPolling = new ForumPolling(HttpClient);
window.ForumReportModal = new ForumReportModal(HttpClient, NotificationService, ModalManager);
window.ForumEditHistory = new ForumEditHistory(ModalManager);
window.NotificationBell = new NotificationBell(HttpClient);
window.AuditLogDiffModal = new AuditLogDiffModal(ModalManager);

document.addEventListener('DOMContentLoaded', () => {
    // JavaScript modules auto-bind DOM handlers upon load
    window.CsvImporter.init();
    window.ModuleReorder.init();
    window.SmartInvitationForm.init();
    window.LessonPlayer.init();
    window.QuizBuilder.init();
    window.QuizTimer.init();
    window.ForumPolling.init();
    window.ForumReportModal.init();
    window.ForumEditHistory.init();
    window.NotificationBell.init();
    window.AuditLogDiffModal.init();
});
