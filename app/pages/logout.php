<?php
$t = (string)get('t');
if (current_user() && hash_equals(csrf_token(), $t)) {
    Auth::logout();
    flash('info', t('Has cerrado sesión. ¡Hasta pronto!'));
}
redirect(url());
