function formatToTwoDigits(nr) {
        nr = "" + nr;
        while (nr.length < 2) {
                nr = "0" + nr;
        }
        return nr;
}

function formatISOTime(time) {
        var date = new Date(time);
        return formatToTwoDigits(date.getHours()) + ':' + formatToTwoDigits(date.getMinutes()) + ':' + formatToTwoDigits(date.getSeconds());
}

var translate, subRoomId, replaceSmileys;

ilAddOnLoad(function() {

        function closeMenus() {
            $('.menu_attached').removeClass('menu_attached');
            $('.menu').hide();
        }
    
	return function($) {

		$.getAsObject = function(data) {
			if (typeof data == 'object') {
				return data;
			}
			try {
				return JSON.parse(data);
			}
			catch(e) {
				if (typeof console != 'undefined') {
					console.log(e);
					return {success: false};
				}
			}
		}

		$.fn.chat = function(lang, baseurl, session_id, instance, scope, posturl, initial) {

			// keep session open
			window.setInterval(function() {
				$.get(posturl.replace(/postMessage/, 'poll'));
			},  120 * 1000);

                        personalUserInfo = initial.userinfo;
                        
                        translate = function(key) {
				if (lang[key]) {
					var lng = lang[key];
					if(typeof arguments[1] != 'undefined') {
						for (var i in arguments[1]) {
							lng = lng.replace(new RegExp('#' + i + '#', 'g'), arguments[1][i]);
						}
					}
					return lng;
				}
				return '#' + key + '#';
			}

                        var prevSize = {width: 0, height: 0};
                        window.setInterval(function() {
                            var currentSize = {width: $('body').width(), height: $('body').height()};
                            if (currentSize.width != prevSize.width || currentSize.height != prevSize.height) {
                                $('#chat_sidebar_wrapper').height($('#chat_sidebar').parent().height() - $('#chat_sidebar_tabs').height());
                                prevSize = {width: $('body').width(), height: $('body').height()};
                            }
                        }, 500);



                        $('#chat_users').ilChatList([
                            {
                                label: translate('address'),
                                callback: function() {
                                        setRecipientOptions(this.id, 1);
                                }
                            },
                            {
                                label: translate('whisper'),
                                callback: function() {
                                        setRecipientOptions(this.id, 0);
                                }
                            },
                            {
                                label: translate('kick'),
                                callback: function() {
					if (subRoomId) {
						alert('kick from private rooms coming soon.')
					}
                                        else if (confirm(translate('kick'))) {
                                                kickUser(this.id);
                                        }
                                },
                                permission: ['moderator']
                            },
                            {
                                label: translate('ban'),
                                callback: function() {
					if (subRoomId) {
						alert('banning from private rooms coming soon.')
					}
                                        else if (confirm(translate('ban'))) {
                                                banUser(this.id);
                                        }
                                },
                                permission: ['moderator']
                            }
                        ]);
                        $('#private_rooms').ilChatList([
                            {
                                label: translate('enter'),
                                callback: function() {
                                        var room = this;
					$.get(
						posturl.replace(/postMessage/, 'privateRoom-enter') + '&sub=' + room.id,
						function(response)
						{
							if (typeof response != 'object') {
								response = $.getAsObject(response);
							}
							
							if (!response.success) {
								alert(response.reason);
							}

							subRoomId = room.id;

							$('#chat_messages').ilChatMessageArea('show', room.id, posturl);

						},
						'json'
					)
                                }
                            },
                            {
                                label: translate('leave'),
                                callback: function() {
                                    var room = this;
					$.get(
						posturl.replace(/postMessage/, 'privateRoom-leave') + '&sub=' + room.id,
						function(response)
						{
							if (typeof response != 'object') {
								response = $.getAsObject(response);
							}

							if (!response.success) {
								alert(response.reason);
							}

                                                        $('#chat_messages').ilChatMessageArea('show', 0);

						},
						'json'
					)
                                }
                            },
                            {
                                label: translate('delete'),
                                callback: function() {
                                    var room = this;
                                    $.get(
                                            posturl.replace(/postMessage/, 'privateRoom-delete') + '&sub=' + room.id,
                                            function(response)
                                            {
                                                    if (typeof response != 'object') {
                                                            response = $.getAsObject(response);
                                                    }

                                                    if (!response.success) {
                                                            alert(response.reason);
                                                    }
                                            },
                                            'json'
                                    )
                                },
                                permission: ['moderator', 'owner']
                            },
                            
                        ]);
                        
                        
                        
                        $('#chat_messages').ilChatMessageArea();
                        
                        $('#chat_messages').ilChatMessageArea('addScope', 0, {
                            title: translate('main'),
                            id: 0,
                            owner: 0
                        });

                        $('#chat_messages').ilChatMessageArea('show', 0);

                        $(initial.messages).each(function() {
                            $('#chat_messages').ilChatMessageArea('addMessage', 0, {
                                type: this.type,
                                message: this.message
                            });
                        });

 
			var polling_url = baseurl + '/frontend/Poll/' + instance + '/' + scope + '?id=' + session_id;
			
			var messageOptions = {
				'recipient' : null,
				'public' : 1
			};

			$('#enter_main').click(function(e) {
                                e.preventDefault();
                                e.stopPropagation();
				subRoomId = 0;
                                $('#chat_messages').ilChatMessageArea('show', 0);
				$('#chat_users').find('.online_user').show();
			});

			$('#submit_message').click(function() {
				submitMessage();
			});

			$('#submit_message_text').keydown(function(e) {
				if (e.keyCode == 13) {
					submitMessage();
				}
			});
			$('#tab_users').click(function(e) {
                            e.stopPropagation();
                            e.preventDefault();
                                closeMenus();
				$([$('#tab_users'), $('#tab_users').parent()]).each(function() {
					this.removeClass('tabinactive').addClass('tabactive');
				});
				$([$('#tab_rooms'), $('#tab_rooms').parent()]).each(function() {
					this.removeClass('tabactive').addClass('tabinactive');
				});
				
				$('#chat_users').css('display', 'block');
				$('#private_rooms_wrapper').css('display', 'none');
			});

			$('#tab_users').click();

                        $(initial.users).each(function() {
                            $('#chat_users').ilChatList('add', {
                                id: this.id,
                                label: this.login,
                                type: 'user'
                            })
                        });

                        $(initial.private_rooms).each(function() {
                                $('#private_rooms').ilChatList('add', {
                                    id: this.proom_id,
                                    label: this.title,
                                    type: 'room',
                                    owner: this.owner
                                });
                                $('#chat_messages').ilChatMessageArea('addScope', this.proom_id, this);
                        });
                        
                        if (initial.enter_room) {
                            $('#chat_messages').ilChatMessageArea('show', initial.enter_room, posturl);
                        }
                        
                        smileys = initial.smileys;

			function setRecipientOptions(recipient, isPublic) {
				messageOptions['recipient'] = recipient;
				messageOptions['public'] = isPublic;

				$('#message_recipient_info').children().remove();
				if(recipient) {
					$('#message_recipient_info').append(
						$('<span>' + translate(isPublic ? 'speak_to' : 'whisper_to', {
							user: $('#chat_users').ilChatList('getDataById', recipient).label
						}) + '</span>')
						.append(
							$('<span>('+translate('cancel')+')</span>').click(
								function() {
									setRecipientOptions(false, 1);
								}
								)
							)
						);
				}
			}

			function buildMoreOptions() {
				var res = [];
				for(var i in messageOptions) {
					if (messageOptions[i] == null || messageOptions[i] == false)
						continue;
					res.push(i + '=' + encodeURIComponent(messageOptions[i]));
				}
				if (subRoomId)
					res.push('sub=' + subRoomId);
				return res.join('&');
			}

			function submitMessage() {
				var format = {
					'color' : $('#colorpicker').val(),
					'style' : $('#fontstyle').val(),
					'size'  : $('#fontsize').val(),
					'family': $('#fontfamily').val()
				};

				var message = {
					'content': $('#submit_message_text').val(),
					'format': format
				}
				if (!message.content.replace (/^\s+/, '').replace (/\s+$/, ''))
					return;
		
				$('#submit_message_text').val("");
				$.get(
					posturl + '&message=' + encodeURIComponent(JSON.stringify(message)) + '&' + buildMoreOptions(),
					function(response)
					{
						response = typeof response == 'object' ? response : $.getAsObject(response);
						if (!response.success) {
							alert(response.reason);
						}
					},
					'json'
					);
			}

			function kickUser(userid) {
				var message = userid;
				$.get(posturl.replace(/postMessage/, 'kick') + '&user=' + encodeURIComponent(message) + '&' + buildMoreOptions());
			}

			function banUser(userid) {
				var message = userid;
				$.get(posturl.replace(/postMessage/, 'ban-active') + '&user=' + encodeURIComponent(message) + '&' + buildMoreOptions());
			}

			function handleMessage(message) {
				messageObject = (typeof message == 'object') ? message : $.getAsObject(message);

if (typeof DEBUG != 'undefined' && DEBUG) {
    $('#chat_messages').ilChatMessageArea('addMessage', 0, {
        type: 'notice',
        message: messageObject.type
    });
    console.log(messageObject);
}

				if ((!messageObject.sub && subRoomId) || (subRoomId && subRoomId != messageObject.sub)) {
					$('#chat_actions').addClass('chat_new_events');
					var id = typeof messageObject == 'undefined' ? 0 : messageObject.sub;
					var data = $('#private_rooms').ilChatList('getDataById', id);
					if (data) {
						data.new_events = true;
					}
				}
				
				switch(messageObject.type) {
                                        case 'user_invited':
					    if (messageObject.invited == personalUserInfo.userid) {
						    var room_label;
						    console.log(messageObject);
						    if (messageObject.proom_id) {
							    room_label = $('#private_rooms').ilChatList('getDataById', messageObject.proom_id).label;
						    }
						    else {
							    room_label = translate('main');
						    }

						    $('#chat_messages').ilChatMessageArea('addMessage', subRoomId, {
							type: 'notice',
							message: translate('user_invited_self', {user: $('#chat_users').ilChatList('getDataById', messageObject.inviter).label, room:room_label })
						    });
					    }
					    
					    break;
					case 'private_room_entered':
					    var data = $('#private_rooms').ilChatList('getDataById', messageObject.sub);
					    if (data) {
						    $('#chat_messages').ilChatMessageArea('addMessage', messageObject.sub || 0, {
								type: 'notice',
								message: translate('private_room_entered', {title: data.label})
						    });
					    }
                                            
                                            if (messageObject.user == personalUserInfo.userid) {
                                                $('#chat_messages').ilChatMessageArea('show', messageObject.sub, posturl);
                                            }
                                            
						break;
					case 'private_room_left':
						if (messageObject.user == myId) {
							roomHandler.getRoom(messageObject.sub).removeClass('in_room');
						}
						$('#chat_users').find('.user_' + messageObject.user).hide();
						break;
					case 'private_room_created':
                                                $('#chat_messages').ilChatMessageArea('addScope', messageObject.proom_id, messageObject);
                                                $('#private_rooms').ilChatList('add', {
                                                    id: messageObject.proom_id,
                                                    label: messageObject.title,
                                                    type: 'room',
                                                    owner: messageObject.owner
                                                });
						break;
					case 'private_room_deleted':
                                                var data = $('#private_rooms').ilChatList('getDataById', messageObject.proom_id);
                                                if (data) {
                                                    $('#chat_messages').ilChatMessageArea('addMessage', 0, {
                                                        type: 'notice',
                                                        message: translate('private_room_closed', {title: data.label})
                                                    });
                                                }
                                                
                                                $('#private_rooms').ilChatList('removeById', messageObject.proom_id);
                                                
                                                if(messageObject.proom_id == subRoomId) {
                                                    subRoomId = 0;
                                                    $('#chat_messages').ilChatMessageArea('show', 0);
                                                }
						break;
					case 'message':
                                            $('#chat_messages').ilChatMessageArea('addMessage', messageObject.sub || 0, messageObject);
                                            break;
					case 'disconnected':
                                            $(messageObject.users).each(function(i) {
                                                var data = $('#chat_users').ilChatList('getDataById', messageObject.users[i]);
                                                $('#chat_messages').ilChatMessageArea('addMessage', 0, {
                                                    login: data.label,
                                                    timestamp: messageObject.timestamp,
                                                    type: 'disconnected'
                                                });
                                                $('#chat_users').ilChatList('removeById', messageObject.users[i]);
                                            });
                                            break;
					case 'connected':
						$(messageObject.users).each(function(i) {
                                                    var data = {
                                                        id: this.id,
                                                        label: this.login,
                                                        type: 'user'
                                                    };
                                                    $('#chat_users').ilChatList('add', data);
						    
						    if (subRoomId) {
							    $('.user_' + this.id).hide();
						    }

                                                    $('#chat_messages').ilChatMessageArea('addMessage', 0, {
                                                        login: data.label,
                                                        timestamp: messageObject.timestamp,
                                                        type: 'connected'
                                                    });
						});
						break;
					default:

				}
			}

			var last_poll_position = -1;
			
			function poll() {
				$.get(
					polling_url,
					{
						pos: last_poll_position,
						id: session_id
					},
					function(response) {
						if (!response || !response.subscribed) {
							window.location.href = initial.redirect_url;
							return false;
						}
						if (response && response.messages) {
							$(response.messages).each(function(i) {
								handleMessage(response.messages[i]);
							});
							last_poll_position = response.next_position;
						}
						if ($('#chat_auto_scroll').is(':checked')) {
							$('#chat_messages').scrollTop(1000000);
						}
						window.setTimeout(poll, 500);
					},
					'jsonp'
					);
			}

			$('#chat_actions').click(function() {
				$(this).removeClass('chat_new_events');

				var menuEntries = [];
var room;

if (subRoomId) {
menuEntries.push(
	                   {
                                label: translate('leave'),
                                callback: function() {
					$.get(
						posturl.replace(/postMessage/, 'privateRoom-leave') + '&sub=' + room.id,
						function(response)
						{
							if (typeof response != 'object') {
								response = $.getAsObject(response);
							}

							if (!response.success) {
								alert(response.reason);
							}

                                                        $('#chat_messages').ilChatMessageArea('show', 0);

						},
						'json'
					)
                                }
                            }
);
}

if (subRoomId && ((room = $('#private_rooms').ilChatList('getDataById', subRoomId)).owner == personalUserInfo.userid) || personalUserInfo.moderate == true) {

menuEntries.push(
                            {
                                label: translate('delete'),
                                callback: function() {
                                    $.get(
                                            posturl.replace(/postMessage/, 'privateRoom-delete') + '&sub=' + room.id,
                                            function(response)
                                            {
                                                    if (typeof response != 'object') {
                                                            response = $.getAsObject(response);
                                                    }

                                                    if (!response.success) {
                                                            alert(response.reason);
                                                    }
                                            },
                                            'json'
                                    )
                                },
                                permission: ['moderator', 'owner']
                            }
			    )
}

menuEntries.push(
	{
		label: translate('create_private_room'),
		callback: function() {
			$('#create_private_room_dialog').ilChatDialog({
					title: translate('create_private_room'),
					positiveAction: function() {
						if ($('#new_room_name').val().trim() == '') {
							alert(translate('empty_name'));
							return false;
						}
						else {
							$.get(
								posturl.replace(/postMessage/, 'privateRoom-create') + '&title=' + encodeURIComponent($('#new_room_name').val()),
								function(response)
								{
									response = typeof response == 'object' ? response : $.getAsObject(response);
									if (!response.success) {
										alert(response.reason);
									}
								},
								'json'
							);
						}
					}
				});
		}
	}
);

menuEntries.push(
{
                                label: translate('invite_users'),
                                callback: function() {

					var invitationChangeTimeout;
                                    $('#invite_users_container')
					.ilChatDialog({
						title: translate('invite_users'),
						close: function() {
							if (invitationChangeTimeout) {
								window.clearInterval(invitationChangeTimeout);
								invitationChangeTimeout = undefined;
							}
						},
						positiveAction: function() {
							
						}
					});

					var sendInvitation = function(user, type, username) {
						$.get(
							posturl.replace(/postMessage/, 'inviteUsersToPrivateRoom-' + type) + (subRoomId ? ('&sub=' + subRoomId) : '') + '&users=' + user,
							function() {
								$('#chat_messages').ilChatMessageArea('addMessage', -1, {
								    type: 'notice',
								    message: translate('user_invited', {user: username})
								});
								$('#invite_users_container').ilChatDialog('close');
							}
						);
					}

                                        $('#invite_users_in_room').click(function() {
						$('#invite_user_text_wrapper').hide();

						$('#invite_users_available').children().remove();

						$.each($('#chat_users').ilChatList('getAll'), function() {
							var id = this.id;
							$('#invite_users_available').append(
							    $('<li class="invite_user_line_id invite_user_line">')
								.append($('<a href="#">')
									.text(this.label)
									.click(function(e) {
										e.preventDefault();
										e.stopPropagation();
										sendInvitation(id, 'byId', $(this).text());
									})
								)
							);
						});
                                        });

					$('#invite_users_in_room').click();
					
                                        $('#invite_users_global').click(function() {
                                            $('#invite_user_text_wrapper').show();
                                            $('#invite_users_available').children().remove();
                                        });

					var cb;
					if (invitationChangeTimeout) {
						window.clearTimeout(invitationChangeTimeout);
					}

					var oldValue = $('#invite_user_text').val();
					invitationChangeTimeout = window.setTimeout(cb = function() {
						if ($('#invite_user_text').val() != oldValue) {
							oldValue = $('#invite_user_text').val();
							$.get(
								posturl.replace(/postMessage/, 'inviteUsersToPrivateRoom-getUserList') + '&q=' + $('#invite_user_text').val(),
								function(response)
								{
									response = $.getAsObject(response);
									$('#invite_users_available').html('');
									
									if (response.response.results) {
										$.each(response.response.results, function() {
											var login = this.login;
											$('<li class="invite_user_line_login invite_user_line">')
											.append($('<a href="#">')
												.text(this.lastname + ', ' + this.firstname + ' [' + this.login + ']')
												.click(function(e) {
													e.preventDefault();
													e.stopPropagation();
													sendInvitation(login, 'byLogin', login);
												})
											).appendTo($('#invite_users_available'));
										});
									}
									invitationChangeTimeout = window.setTimeout(cb, 500);
								},
								'json');
						}
						else {
							invitationChangeTimeout = window.setTimeout(cb, 300);
						}
					}, 500);

                                }
                            }
			);

menuEntries.push({separator: true});
var rooms = [{
	label: translate('main'),
	id: 0,
	owner: 0,
	addClass: 'room_0' + (!subRoomId ? ' in_room' : '')
}].concat($('#private_rooms').ilChatList('getAll'));

rooms.sort(function(a,b) {

	if (a.id == 0) {
		return -1;
	}
	else if (b.id == 0) {
		return 1;
	}

	return a.label < b.label ? -1 : 1;
});

$.each(rooms, function() {
	var room = this;
	var classes = ['room_' + room.id];

	if (subRoomId == room.id) {
		classes.push('in_room');
	}
	if (room.new_events) {
		classes.push('chat_new_events');
	}

	menuEntries.push({
		label: this.label,
		icon: 'templates/default/images/' + (!room.id ? 'icon_chtr_s.gif' : 'icon_chtr_private_s.gif'),
		addClass: classes.join(' '),
		callback: function() {
			if (!room.id) {
				$('#chat_messages').ilChatMessageArea('show', 0);
				return;
			}
			room.new_events = false;
			$.get(
				posturl.replace(/postMessage/, 'privateRoom-enter') + '&sub=' + room.id,
				function(response)
				{
					if (typeof response != 'object') {
						response = $.getAsObject(response);
					}

					if (!response.success) {
						alert(response.reason);
					}

					subRoomId = room.id;

					$('#chat_messages').ilChatMessageArea('show', room.id, posturl);

					if (subRoomId) {
						$('#chat_users').find('.online_user').hide();

						$.get(
							posturl.replace(/postMessage/, 'privateRoom-listUsers') + '&sub=' + room.id,
							function(response)
							{
								response = typeof response == 'object' ? response : $.getAsObject(response);

								$.each(response, function() {
									$('#chat_users').find('.user_' + this).show();
								});

								if (!$('#chat_messages').ilChatMessageArea('hasContent', room.id)) {
								    $('#chat_messages').ilChatMessageArea('addMessage', room.id, {
										type: 'notice',
										message: translate('private_room_entered', {title: room.label})
								    });
								}
							},
							'json'
							);
						}
						else {
							$('#chat_users').find('.online_user').show();
						}
					},
					'json'
				)
			}
		});
	});

				$(this).ilChatMenu('show', menuEntries);
			});

			window.setTimeout(function() {
				$('#chat_messages').ilChatMessageArea('addMessage', 0, {
				    type: 'notice',
				    message: translate('welcome_to_chat')
				});
				poll();
			}, 10);
			var smileys = initial.smileys;

			replaceSmileys = function (message) 
			{
				var replacedMessage = message;
				
				for (var i in smileys)
				{
					while( replacedMessage.indexOf(i) != -1 )
					{
						replacedMessage = replacedMessage.replace(i, '<img src="' + smileys[i] + '" />');
					}
				}
				
				return replacedMessage;
			}
		}
	}(jQuery)
});