/* Small behaviours used across the site (friends, catalog, profile settings). */
var BOBLOX = window.BOBLOX || {};
window.BOBLOX = BOBLOX;

BOBLOX.logout = function () {
  BOBLOX.Ajax.call('Users', 'Logout', {}, function (r) { window.location = r.redirect; });
};

BOBLOX.setBusy = function (el, busy, text) {
  if (!el) return;
  if (busy) {
    el.setAttribute('data-label', el.innerHTML);
    el.innerHTML = text || 'Please wait...';
    el.className += ' Busy';
  } else {
    if (el.getAttribute('data-label') !== null) el.innerHTML = el.getAttribute('data-label');
    el.className = el.className.replace(/\s*Busy/g, '');
  }
};

BOBLOX.Friends = {
  labels: {
    none: 'Send Friend Request',
    sent: 'Friend Request Sent (cancel)',
    received: 'Accept Friend Request',
    friends: 'Remove Friend'
  },

  // Profile page button: does the right thing for the current status.
  toggle: function (button, userId) {
    var status = button.getAttribute('data-status');
    var method = status === 'none' ? 'RequestFriendship'
      : status === 'received' ? 'AcceptFriendship'
      : 'RemoveFriendship';
    if (status === 'friends' && !confirm('Are you sure you want to remove this friend?')) return;
    BOBLOX.setBusy(button, true);
    BOBLOX.Ajax.call('Friends', method, { userId: userId }, function (r) {
      button.setAttribute('data-status', r.status);
      button.setAttribute('data-label', BOBLOX.Friends.labels[r.status]);
      BOBLOX.setBusy(button, false);
    }, function (msg) {
      BOBLOX.setBusy(button, false);
      alert(msg);
    });
  },

  // "My BOBLOX" friend request rows
  answer: function (userId, accept) {
    BOBLOX.Ajax.call('Friends', accept ? 'AcceptFriendship' : 'RemoveFriendship', { userId: userId }, function (r) {
      var row = $get('FriendRequest_' + userId);
      if (row) {
        row.innerHTML = '<td colspan="3" class="RequestAnswered">' + (accept ? 'You are now friends!' : 'Request declined.') + '</td>';
      }
      var alert = $get('MessageAlert');
      if (alert) {
        if (r.requests > 0) alert.getElementsByTagName('a')[0].innerHTML = r.requests + ' Friend Request' + (r.requests === 1 ? '' : 's');
        else $get('AlertSpace').style.display = 'none';
      }
    });
  }
};

BOBLOX.Catalog = {
  take: function (button, itemId, wear) {
    BOBLOX.setBusy(button, true);
    BOBLOX.Ajax.call('Catalog', wear ? 'TakeAndWearItem' : 'TakeItem', { itemId: itemId }, function () {
      window.location.reload();
    }, function (msg) {
      BOBLOX.setBusy(button, false);
      alert(msg);
    });
  },

  remove: function (button, itemId) {
    if (!confirm('Delete this item? It will be removed from everyone who owns it.')) return;
    BOBLOX.setBusy(button, true);
    BOBLOX.Ajax.call('Catalog', 'DeleteItem', { itemId: itemId }, function (r) {
      window.location = r.redirect;
    }, function (msg) {
      BOBLOX.setBusy(button, false);
      alert(msg);
    });
  }
};

BOBLOX.Profile = {
  save: function () {
    var status = $get('ProfileStatus');
    status.className = 'StatusMessage';
    status.innerHTML = 'Saving...';
    BOBLOX.Ajax.call('Users', 'UpdateProfile', {
      blurb: $get('Blurb').value,
      discord: $get('Discord').value
    }, function () {
      status.innerHTML = 'Your profile was saved.';
    }, function (msg) {
      status.className = 'StatusMessage Error';
      status.innerHTML = msg.replace(/</g, '&lt;');
    });
  },

  changePassword: function () {
    var status = $get('PasswordStatus');
    status.className = 'StatusMessage';
    if ($get('NewPassword').value !== $get('ConfirmPassword').value) {
      status.className = 'StatusMessage Error';
      status.innerHTML = 'The new passwords do not match.';
      return;
    }
    status.innerHTML = 'Saving...';
    BOBLOX.Ajax.call('Users', 'ChangePassword', {
      oldPassword: $get('OldPassword').value,
      newPassword: $get('NewPassword').value
    }, function () {
      status.innerHTML = 'Your password was changed.';
      $get('OldPassword').value = $get('NewPassword').value = $get('ConfirmPassword').value = '';
    }, function (msg) {
      status.className = 'StatusMessage Error';
      status.innerHTML = msg.replace(/</g, '&lt;');
    });
  },

  // Swap the profile picture for the live 3D figure
  show3D: function (link, userId) {
    var holder = $get('Avatar3DHolder'), img = $get('AvatarImage');
    if (!window.THREE || !BOBLOX.Avatar3D || !BOBLOX.Avatar3D.isSupported()) {
      alert('Your browser does not support 3D.');
      return;
    }
    if (holder.style.display === 'none') {
      holder.style.display = 'block';
      img.style.display = 'none';
      link.innerHTML = '[ Show Picture ]';
      if (!holder.viewer) {
        holder.viewer = new BOBLOX.Avatar3D(holder, { width: 250, height: 250 });
        holder.viewer.setAppearance(window.ProfileAppearance);
      }
    } else {
      holder.style.display = 'none';
      img.style.display = '';
      link.innerHTML = '[ View in 3D ]';
    }
  }
};
