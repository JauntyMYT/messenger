(function () {
	var root = document.getElementById('jauntym-mess');
	if (!root) { return; }

	var AJAX  = root.getAttribute('data-ajax');
	try { var _u=document.createElement('a'); _u.href=AJAX; AJAX=_u.pathname + (_u.search||''); } catch(e){}
	var SID = root.getAttribute('data-sid') || '';
	var TOKEN = root.getAttribute('data-token');
	var ME    = parseInt(root.getAttribute('data-me'), 10);
	var POLL  = parseInt(root.getAttribute('data-poll'), 10) || 15000;
	var MINSEARCH = parseInt(root.getAttribute('data-minsearch'), 10) || 2;
	var TYPING_ON = root.getAttribute('data-typing') === '1';

	function lng(k){ return root.getAttribute('data-l-' + k) || ''; }
	var LANG = {
		seen:lng('seen'), sent:lng('sent'), online:lng('online'),
		none:lng('none'), empty:lng('empty'), older:lng('older'),
		people:lng('people'), typing:lng('typing'), menu:lng('menu'),
		block:lng('block'), unblock:lng('unblock'), hide:lng('hide'),
		cdel:lng('cdel'), cblock:lng('cblock'), del:lng('del'),
		generic:lng('generic')
	};

	var convListEl = document.getElementById('jauntym-convlist');
	var threadEl   = document.getElementById('jauntym-thread');
	var headEl     = document.getElementById('jauntym-head');
	var paneEl     = document.getElementById('jauntym-pane');
	var placeEl    = document.getElementById('jauntym-placeholder');
	var inputEl    = document.getElementById('jauntym-input');
	var sendBtn    = document.getElementById('jauntym-send');

	var current = { convId:0, pendingUser:0, partner:null, oldest:0, lastReadId:0, readTime:'', typing:false };
	var allConvs = [];
	var lastTypingPing = 0;

	function esc(s){ var d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; }
	function initials(name){ name=(name||'?').trim(); var p=name.split(/\s+/); return ((p[0]?p[0][0]:'?')+(p[1]?p[1][0]:'')).toUpperCase(); }
	function avatar(html, name, small){
		var cls='jauntym-av'+(small?' s':'');
		if (html && html.indexOf('<img')!==-1){ return '<span class="'+cls+'">'+html+'</span>'; }
		var hue=0; for (var i=0;i<(name||'').length;i++){ hue=(hue+name.charCodeAt(i)*7)%360; }
		return '<span class="'+cls+'" style="background:hsl('+hue+',42%,55%)">'+esc(initials(name))+'</span>';
	}

	function api(action, params, method){
		method=method||'GET';
		var url=AJAX+(AJAX.indexOf('?')===-1?'?':'&')+'action='+encodeURIComponent(action);
		if(SID){ url+='&sid='+encodeURIComponent(SID); }
		var opts={method:method,headers:{'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'};
		if (method==='GET'){ for (var k in params){ if(params.hasOwnProperty(k)){ url+='&'+k+'='+encodeURIComponent(params[k]); } } }
		else { var b=[]; for (var p in params){ if(params.hasOwnProperty(p)){ b.push(encodeURIComponent(p)+'='+encodeURIComponent(params[p])); } } opts.body=b.join('&'); opts.headers['Content-Type']='application/x-www-form-urlencoded'; }
		return fetch(url,opts).then(function(r){ return r.json(); }).catch(function(){ return {ok:false,error:LANG.generic}; });
	}

	/* conversation list */
	function loadConversations(){
		api('conversations').then(function(res){ if(!res||!res.ok){return;} allConvs=res.conversations||[]; renderConvList(filterConvs()); });
	}
	function filterConvs(){
		var v=(document.getElementById('jauntym-search-conv').value||'').toLowerCase();
		return v ? allConvs.filter(function(c){ return c.name.toLowerCase().indexOf(v)!==-1; }) : allConvs;
	}
	function renderConvList(list){
		if(!list.length){ convListEl.innerHTML='<div class="jauntym-empty" style="height:auto;padding:30px 18px">'+esc(LANG.none)+'</div>'; return; }
		var html='';
		for (var i=0;i<list.length;i++){
			var c=list[i];
			var snip=(c.snippet_mine?'<span style="color:#999">You: </span>':'')+esc(c.snippet);
			html+='<div class="jauntym-conv'+(c.conv_id===current.convId?' jauntym-active':'')+(c.unread>0?' jauntym-unread':'')+'" data-conv="'+c.conv_id+'">'
				+avatar(c.avatar,c.name,false)
				+'<div class="jauntym-meta"><div class="jauntym-row1"><span class="jauntym-name">'+esc(c.name)+'</span><span class="jauntym-time">'+esc(c.time)+'</span></div>'
				+'<div class="jauntym-snippet">'+snip+'</div></div>'
				+(c.unread>0?'<span class="jauntym-badge">'+c.unread+'</span>':'')+'</div>';
		}
		convListEl.innerHTML=html;
		var nodes=convListEl.querySelectorAll('.jauntym-conv');
		for (var n=0;n<nodes.length;n++){ nodes[n].addEventListener('click', function(){ openConversation(parseInt(this.getAttribute('data-conv'),10)); }); }
	}

	/* open thread */
	function openConversation(convId){
		current.convId=convId; current.pendingUser=0; current.oldest=0;
		placeEl.classList.add('jauntym-hidden'); paneEl.classList.remove('jauntym-hidden'); root.classList.add('jauntym-show-thread');
		threadEl.innerHTML=''; fetchMessages(0,true); highlightActive();
	}
	function highlightActive(){
		var nodes=convListEl.querySelectorAll('.jauntym-conv');
		for (var i=0;i<nodes.length;i++){
			var id=parseInt(nodes[i].getAttribute('data-conv'),10);
			nodes[i].classList.toggle('jauntym-active',id===current.convId);
			if(id===current.convId){ nodes[i].classList.remove('jauntym-unread'); var b=nodes[i].querySelector('.jauntym-badge'); if(b){b.remove();} }
		}
	}
	function fetchMessages(before, scrollBottom){
		var params={conv_id:current.convId}; if(before){params.before=before;}
		api('messages',params).then(function(res){
			if(!res||!res.ok){ if(res&&res.error){alert(res.error);} return; }
			current.partner=res.partner; current.lastReadId=res.partner_read_id||0; current.readTime=res.partner_read_time||''; current.typing=!!res.partner_typing;
			renderHead(res.partner);
			if(before){ prependMessages(res.messages,res.has_more); }
			else { renderThread(res.messages,res.has_more,scrollBottom); }
		});
	}

	function renderHead(p){
		if(!p){ headEl.innerHTML=''; return; }
		var sub;
		if(current.typing){ sub='<span class="jauntym-hsub typing">'+esc(LANG.typing)+'</span>'; }
		else if(p.online){ sub='<span class="jauntym-hsub"><span class="jauntym-dot on"></span>'+esc(LANG.online)+'</span>'; }
		else { sub='<span class="jauntym-hsub"><span class="jauntym-dot"></span>'+esc(p.last_seen||'')+'</span>'; }
		headEl.innerHTML=avatar(p.avatar,p.name,false)
			+'<div style="flex:1;min-width:0"><div class="jauntym-hname">'+esc(p.name)+'</div>'+sub+'</div>'
			+'<button type="button" class="jauntym-kebab" id="jauntym-kebab" aria-label="'+esc(LANG.menu)+'"><i class="icon fa-ellipsis-v" aria-hidden="true"></i></button>';
		var kb=document.getElementById('jauntym-kebab'); if(kb){ kb.addEventListener('click', toggleMenu); }
	}

	function toggleMenu(e){
		e.stopPropagation();
		var ex=headEl.querySelector('.jauntym-menu'); if(ex){ ex.remove(); return; }
		var p=current.partner; if(!p){ return; }
		var menu=document.createElement('div'); menu.className='jauntym-menu';
		var blockLabel=p.i_blocked?LANG.unblock:LANG.block;
		menu.innerHTML='<button type="button" data-act="hide">'+esc(LANG.hide)+'</button>'
			+'<button type="button" class="danger" data-act="block">'+esc(blockLabel)+'</button>';
		headEl.appendChild(menu);
		menu.querySelector('[data-act="hide"]').addEventListener('click', function(){ hideConv(); });
		menu.querySelector('[data-act="block"]').addEventListener('click', function(){ toggleBlock(p); });
	}
	document.addEventListener('click', function(){ var m=headEl.querySelector('.jauntym-menu'); if(m){ m.remove(); } });

	function hideConv(){
		if(!current.convId){return;}
		api('hide',{conv_id:current.convId}).then(function(){
			paneEl.classList.add('jauntym-hidden'); placeEl.classList.remove('jauntym-hidden'); root.classList.remove('jauntym-show-thread');
			current.convId=0; loadConversations();
		});
	}
	function toggleBlock(p){
		var act=p.i_blocked?'unblock':'block';
		if(act==='block' && !confirm(LANG.cblock)){ return; }
		api(act,{user_id:p.user_id}).then(function(res){
			if(!res||!res.ok){ alert(res&&res.error?res.error:LANG.generic); return; }
			current.partner.i_blocked=res.blocked; var m=headEl.querySelector('.jauntym-menu'); if(m){m.remove();}
		});
	}

	function bubbleHtml(m){
		var del=(m.mine && !m.deleted)?'<button type="button" class="jauntym-del" data-del="'+m.msg_id+'" title="'+esc(LANG.del)+'"><i class="icon fa-trash-o" aria-hidden="true"></i></button>':'';
		return '<div class="jauntym-line '+(m.mine?'me':'them')+'" data-mid="'+m.msg_id+'">'+del+'<div class="jauntym-bub">'+m.html+'</div></div>';
	}
	function bindThread(){
		var dels=threadEl.querySelectorAll('.jauntym-del');
		for(var i=0;i<dels.length;i++){ dels[i].addEventListener('click', function(){ delMessage(parseInt(this.getAttribute('data-del'),10)); }); }
		var older=threadEl.querySelector('#jauntym-older'); if(older){ older.addEventListener('click', function(){ fetchMessages(current.oldest,false); }); }
	}
	function renderThread(messages, hasMore, scrollBottom){
		var html=hasMore?'<button type="button" class="jauntym-loadmore" id="jauntym-older">'+esc(LANG.older)+'</button>':'';
		if(!messages.length){ html+='<div class="jauntym-empty" style="height:auto">'+esc(LANG.empty)+'</div>'; }
		for(var i=0;i<messages.length;i++){ html+=bubbleHtml(messages[i]); }
		threadEl.innerHTML=html;
		if(messages.length){ current.oldest=messages[0].msg_id; }
		bindThread(); applyReceipt();
		if(scrollBottom){ threadEl.scrollTop=threadEl.scrollHeight; }
	}
	function prependMessages(messages, hasMore){
		var keepH=threadEl.scrollHeight, keepT=threadEl.scrollTop;
		var older=threadEl.querySelector('#jauntym-older'); if(older){ older.remove(); }
		var frag=hasMore?'<button type="button" class="jauntym-loadmore" id="jauntym-older">'+esc(LANG.older)+'</button>':'';
		for(var i=0;i<messages.length;i++){ frag+=bubbleHtml(messages[i]); }
		threadEl.insertAdjacentHTML('afterbegin',frag);
		if(messages.length){ current.oldest=messages[0].msg_id; }
		bindThread();
		threadEl.scrollTop=threadEl.scrollHeight-keepH+keepT;
	}
	function delMessage(mid){
		if(!confirm(LANG.cdel)){ return; }
		api('delete_message',{msg_id:mid},'POST').then(function(res){
			if(!res||!res.ok){ alert(res&&res.error?res.error:LANG.generic); return; }
			fetchMessages(0,false); loadConversations();
		});
	}
	function applyReceipt(){
		var old=threadEl.querySelector('.jauntym-receipt'); if(old){ old.remove(); }
		var mine=threadEl.querySelectorAll('.jauntym-line.me'); if(!mine.length){ return; }
		var last=mine[mine.length-1]; var mid=parseInt(last.getAttribute('data-mid'),10);
		var label=(current.lastReadId>=mid && current.readTime)?(LANG.seen+' '+current.readTime):LANG.sent;
		var div=document.createElement('div'); div.className='jauntym-receipt'; div.textContent=label;
		last.insertAdjacentElement('afterend',div);
	}

	/* sending */
	function send(){
		if(!inputEl){return;}
		var text=inputEl.value.replace(/\s+$/,'');
		if(!text || (!current.convId && !current.pendingUser)){ return; }
		sendBtn.disabled=true;
		var params={text:text,hash:TOKEN,conv_id:current.convId||0};
		if(!current.convId && current.pendingUser){ params.to_user=current.pendingUser; }
		api('send',params,'POST').then(function(res){
			sendBtn.disabled=false;
			if(!res||!res.ok){ alert(res&&res.error?res.error:LANG.generic); return; }
			inputEl.value=''; autoGrow();
			if(!current.convId){ current.convId=res.conv_id; current.pendingUser=0; }
			threadEl.insertAdjacentHTML('beforeend',bubbleHtml(res.message));
			bindThread(); current.lastReadId=0; applyReceipt();
			threadEl.scrollTop=threadEl.scrollHeight; loadConversations();
		});
	}

	/* typing ping */
	function pingTyping(){
		if(!TYPING_ON || !current.convId){ return; }
		var now=Date.now();
		if(now-lastTypingPing < 3000){ return; }
		lastTypingPing=now;
		api('typing',{conv_id:current.convId},'POST');
	}

	/* new message / user search */
	function startNew(){
		current.convId=0; current.pendingUser=0; current.partner=null;
		placeEl.classList.add('jauntym-hidden'); paneEl.classList.remove('jauntym-hidden'); root.classList.add('jauntym-show-thread');
		headEl.innerHTML='<input type="text" id="jauntym-userq" placeholder="'+esc(LANG.people)+'" style="flex:1;padding:8px 12px;border:1px solid rgba(0,0,0,.15);border-radius:8px;outline:none;font-size:14px">';
		threadEl.innerHTML='<div id="jauntym-results" style="display:flex;flex-direction:column;gap:2px"></div>';
		var q=document.getElementById('jauntym-userq'); q.focus();
		var t; q.addEventListener('input', function(){ clearTimeout(t); var v=this.value; t=setTimeout(function(){ searchUsers(v); },220); });
	}
	function searchUsers(q){
		var box=document.getElementById('jauntym-results'); if(!box){ return; }
		if(!q || q.trim().length<MINSEARCH){ box.innerHTML=''; return; }
		api('search_users',{q:q}).then(function(res){
			if(!res||!res.ok){ return; }
			var html='';
			for(var i=0;i<res.users.length;i++){ var u=res.users[i];
				html+='<div class="jauntym-conv" data-uid="'+u.user_id+'" style="border-radius:8px">'+avatar(u.avatar,u.name,true)
					+'<div class="jauntym-meta" style="display:flex;align-items:center"><span class="jauntym-name">'+esc(u.name)+'</span></div></div>';
			}
			box.innerHTML=html;
			var nodes=box.querySelectorAll('[data-uid]');
			for(var n=0;n<nodes.length;n++){ nodes[n].addEventListener('click', function(){ pickUser(parseInt(this.getAttribute('data-uid'),10)); }); }
		});
	}
	function pickUser(uid){
		api('start',{user_id:uid}).then(function(res){
			if(!res||!res.ok){ alert(res&&res.error?res.error:LANG.generic); return; }
			loadConversations(); openConversation(res.conv_id); inputEl.focus();
		});
	}

	function autoGrow(){ if(!inputEl){return;} inputEl.style.height='auto'; inputEl.style.height=Math.min(inputEl.scrollHeight,120)+'px'; }

	function poll(){
		if(current.convId){
			api('messages',{conv_id:current.convId}).then(function(res){
				if(!res||!res.ok){ return; }
				current.lastReadId=res.partner_read_id||0; current.readTime=res.partner_read_time||''; current.typing=!!res.partner_typing;
				renderHead(res.partner);
				var atBottom=(threadEl.scrollHeight-threadEl.scrollTop-threadEl.clientHeight)<60;
				var existing=threadEl.querySelectorAll('.jauntym-line').length;
				if(res.messages.length!==existing){ renderThread(res.messages,res.has_more,atBottom); }
				else { applyReceipt(); }
			});
		}
		loadConversations();
	}

	var _newBtn=document.getElementById('jauntym-new'); if(_newBtn){_newBtn.addEventListener('click', startNew);}
	if(sendBtn){sendBtn.addEventListener('click', send);}
	if(inputEl){inputEl.addEventListener('input', function(){ autoGrow(); pingTyping(); });}
	if(inputEl){inputEl.addEventListener('keydown', function(e){ if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); send(); } });}
	document.getElementById('jauntym-search-conv').addEventListener('input', function(){ renderConvList(filterConvs()); });

	loadConversations();
	setInterval(poll, POLL);
	window.addEventListener('focus', poll);
	var deep=location.search.match(/[?&]c=(\d+)/);
	if(deep){ openConversation(parseInt(deep[1],10)); }
})();
