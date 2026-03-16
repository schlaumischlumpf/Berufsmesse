<?php
/**
 * Login-Benachrichtigungen Modal
 * Zeigt ungelesene Benachrichtigungen beim ersten Seitenaufruf nach dem Login
 */

if (isset($_SESSION['pending_notifications']) && !empty($_SESSION['pending_notifications'])):
    $pendingNotifs = $_SESSION['pending_notifications'];
    unset($_SESSION['pending_notifications']); // Nur einmal anzeigen

    // Benachrichtigungen als gelesen markieren
    try {
        $notifIds = array_column($pendingNotifs, 'id');
        if (!empty($notifIds)) {
            $placeholders = implode(',', array_fill(0, count($notifIds), '?'));
            $db->prepare("UPDATE login_notifications SET read_at = NOW() WHERE id IN ($placeholders)")->execute($notifIds);
        }
    } catch (Exception $e) {
        logErrorToAudit($e, 'notifications-modal');
    }
?>

<!-- Benachrichtigungs-Modal -->
<div id="notificationsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" onclick="this.style.display='none'">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[80vh] overflow-hidden" onclick="event.stopPropagation()">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gradient-to-r from-blue-50 to-indigo-50">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                <i class="fas fa-bell text-blue-500"></i>
                Neue Benachrichtigungen (<?php echo count($pendingNotifs); ?>)
            </h3>
            <button onclick="document.getElementById('notificationsModal').style.display='none'"
                    class="text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- Benachrichtigungen -->
        <div class="overflow-y-auto max-h-[60vh] divide-y divide-gray-100">
            <?php foreach ($pendingNotifs as $notif):
                $iconMap = [
                    'exhibitor_cancelled' => ['icon' => 'fa-user-times', 'color' => 'text-red-500', 'bg' => 'bg-red-50'],
                    'school_cancelled' => ['icon' => 'fa-school', 'color' => 'text-orange-500', 'bg' => 'bg-orange-50'],
                    'cancellation_request' => ['icon' => 'fa-question-circle', 'color' => 'text-amber-500', 'bg' => 'bg-amber-50'],
                    'info' => ['icon' => 'fa-info-circle', 'color' => 'text-blue-500', 'bg' => 'bg-blue-50'],
                ];
                $style = $iconMap[$notif['type']] ?? $iconMap['info'];
            ?>
            <div class="p-4 hover:bg-gray-50 transition">
                <div class="flex gap-3">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-full <?php echo $style['bg']; ?> flex items-center justify-center">
                            <i class="fas <?php echo $style['icon'] . ' ' . $style['color']; ?>"></i>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-800"><?php echo htmlspecialchars($notif['message']); ?></p>
                        <p class="text-xs text-gray-400 mt-1">
                            <i class="fas fa-clock mr-1"></i><?php echo date('d.m.Y H:i', strtotime($notif['created_at'])); ?>
                        </p>

                        <?php if ($notif['type'] === 'cancellation_request' && $notif['action_url']): ?>
                        <a href="<?php echo htmlspecialchars($notif['action_url']); ?>"
                           class="inline-flex items-center gap-1 mt-2 px-3 py-1 bg-blue-500 text-white text-xs rounded-lg hover:bg-blue-600 transition">
                            <i class="fas fa-hand-point-right"></i> Zum Antrag
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Footer -->
        <div class="px-6 py-3 border-t border-gray-200 bg-gray-50 text-center">
            <button onclick="document.getElementById('notificationsModal').style.display='none'"
                    class="px-4 py-2 bg-gray-600 text-white text-sm rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-check mr-1"></i> Verstanden
            </button>
        </div>
    </div>
</div>

<?php endif; ?>
