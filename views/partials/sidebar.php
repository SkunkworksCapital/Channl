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
        <span style="display:block;color:#9ca3af;padding:8px 10px;margin-top:8px">WhatsApp</span>
        <a href="/whatsapp/send" style="padding-left:20px">Send WhatsApp</a>
        <a href="/whatsapp/inbox" style="padding-left:20px">Read WhatsApp</a>
        <span style="display:block;color:#9ca3af;padding:8px 10px;margin-top:8px">Email</span>
        <a href="/email/send" style="padding-left:20px">Send Email</a>
        <a href="/email/inbox" style="padding-left:20px">Read Email</a>
        <span style="display:block;color:#9ca3af;padding:8px 10px;margin-top:8px">Templates</span>
        <a href="/templates/sms" style="padding-left:20px">SMS Templates</a>
        <a href="/templates/email" style="padding-left:20px">Email Templates</a>
      <?php else: ?>
        <a href="/login">Login</a>
        <a href="/register">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</aside>


