<?php
// Dark sidebar navigation
?>
<aside class="sidebar">
  <div class="sidebar-inner">
    <nav>
      <a href="/">Dashboard</a>
      <?php if (current_user_id()): ?>
        <a href="/contacts">Contacts</a>
        <a href="/lists">Lists</a>
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
        <span style="display:block;color:#9ca3af;padding:8px 10px;margin-top:8px">Compliance</span>
        <a href="/approvals" style="padding-left:20px">Approvals</a>
        <a href="/exports/audit.csv" style="padding-left:20px">Export Audit CSV</a>
        <a href="/exports/messages.csv" style="padding-left:20px">Export Messages CSV</a>
        <?php endif; ?>
        <span style="display:block;color:#9ca3af;padding:8px 10px;margin-top:8px">Settings</span>
        <a href="/billing" style="padding-left:20px">Credits & Billing</a>
        <a href="/settings" style="padding-left:20px">User Settings</a>
      <?php else: ?>
        <a href="/login">Login</a>
        <a href="/register">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</aside>


