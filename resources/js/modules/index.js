// -----------------------------------------------------------------------------
// Registry único de módulos. Cada chave vira `window.<chave>` e recebe
// `.init()` após o DOMContentLoaded. Este é o ÚNICO arquivo que muda quando um
// módulo entra ou sai — mantenha-o em ordem alfabética para minimizar conflito.
// -----------------------------------------------------------------------------
import AuditLogDiffModal   from './AuditLogDiffModal';
import CsvImporter         from './CsvImporter';
import DashboardFilter     from './DashboardFilter';
import EssayGrading        from './EssayGrading';
import ForumPolling        from './ForumPolling';
import ForumReportModal    from './ForumReportModal';
import HttpClient          from './HttpClient';
import LessonForm          from './LessonForm';
import LessonPlayer        from './LessonPlayer';
import ModuleReorder       from './ModuleReorder';
import NotificationBell    from './NotificationBell';
import NotificationService from './NotificationService';
import PasswordToggle      from './PasswordToggle';
import QuizBuilder         from './QuizBuilder';
import QuizTaking          from './QuizTaking';
import QuizTimer           from './QuizTimer';
import SmartInvitationForm from './SmartInvitationForm';

// ModalManager e ForumEditHistory foram REMOVIDOS: substituídos por
// bootstrap.Modal + data-bs-toggle/data-bs-dismiss.

const httpClient   = HttpClient;          // singleton
const notifications = NotificationService; // singleton (agora sobre bootstrap.Toast)

export default {
    HttpClient:          httpClient,
    NotificationService: notifications,
    AuditLogDiffModal:   new AuditLogDiffModal(),
    CsvImporter:         new CsvImporter(httpClient),
    DashboardFilter:     new DashboardFilter(httpClient),
    EssayGrading:        new EssayGrading(),
    ForumPolling:        new ForumPolling(httpClient),
    ForumReportModal:    new ForumReportModal(httpClient, notifications),
    LessonForm:          new LessonForm(),
    LessonPlayer:        new LessonPlayer(httpClient, notifications),
    ModuleReorder:       new ModuleReorder(httpClient, notifications),
    NotificationBell:    new NotificationBell(httpClient),
    PasswordToggle:      new PasswordToggle(),
    QuizBuilder:         new QuizBuilder(notifications),
    QuizTaking:          new QuizTaking(),
    QuizTimer:           new QuizTimer(),
    SmartInvitationForm: new SmartInvitationForm(httpClient, notifications),
};
