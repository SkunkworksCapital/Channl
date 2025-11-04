<?php
// Dark sidebar navigation
?>
<aside class="sidebar">
  <div class="sidebar-inner">
    <nav>
      <a href="/">Dashboard</a>
      <a href="/analytics">Analytics</a>
      <?php if (current_user_id()): ?>
        <a href="/contacts">Contacts</a>
        <a href="/lists">My Lists</a>
        <span style="display:block;color:#9ca3af;padding:8px 10px;margin-top:8px">SMS</span>
        <a href="/sms/send" style="padding-left:20px">Send SMS</a>
        <a href="/sms/inbox" style="padding-left:20px">Read SMS</a>
        <a href="/templates/sms" style="padding-left:20px">SMS Templates</a>
        <span style="display:block;color:#9ca3af;padding:8px 10px;margin-top:8px">WhatsApp</span>
        <a href="/whatsapp/send" style="padding-left:20px">Send WhatsApp</a>
        <a href="/whatsapp/inbox" style="padding-left:20px">Read WhatsApp</a>
        <span style="display:block;color:#9ca3af;padding:8px 10px;margin-top:8px">Email</span>
        <a href="/email/send" style="padding-left:20px">Send Email</a>
        <a href="/email/inbox" style="padding-left:20px">Read Email</a>
        <a href="/templates/email" style="padding-left:20px">Email Templates</a>
        <?php if (is_admin()): ?>
        <a href="/approvals">Approvals</a>
        <a href="/exports/audit.csv">Export Audit CSV</a>
        <a href="/exports/messages.csv">Export Messages CSV</a>
        <?php endif; ?>
        <a href="/scheduled">Scheduled Jobs</a>
        <a href="/billing">Credits & Billing</a>
        <a href="/settings">User Settings</a>
      <?php else: ?>
        <a href="/login">Login</a>
        <a href="/register">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</aside>



