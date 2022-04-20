/**
 * Listen for admin notices to be dismissed: if both "id" and "nonce" data-attributes are present,
 * trigger a WP-Ajax request to track the dismissal for the current user.
 */

/* global ajaxurl:readonly */
window.addEventListener('click', function (e) {
	if (!e.target.classList.contains('notice-dismiss')) {
		return;
	}

	const notice = e.target.parentElement;

	// Don't send the request if we're missing a nonce and/or notice key.
	if (!notice.dataset.id || !notice.dataset.nonce) {
		return;
	}

	// Issue a POST request to WP-Ajax.
	const body = new FormData();
	body.append('action', 'stellarwp-dismiss-notice');
	body.append('notice', notice.dataset.id);
	body.append('_wpnonce', notice.dataset.nonce);

	this.fetch(ajaxurl, {
		method: 'POST',
		body,
		credentials: 'include',
	});
});
