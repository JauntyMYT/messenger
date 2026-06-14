(function(){
	var dock=document.getElementById('jauntymbd');
	var fab=document.getElementById('jauntymfab');
	if(!dock||!fab){return;}
	var AJAX=dock.getAttribute('data-ajax')||'';
	try{var _u=document.createElement('a');_u.href=AJAX;AJAX=_u.pathname+(_u.search||'');}catch(e){}
	var SID=dock.getAttribute('data-sid')||'', TOKEN=dock.getAttribute('data-token')||'';
	var PAGE=dock.getAttribute('data-page')||'#', POLL=parseInt(dock.getAttribute('data-poll'),10)||15000;
	var ME=parseInt(dock.getAttribute('data-me'),10)||0, CANDM=dock.getAttribute('data-candm')==='1';

	function lng(k){return dock.getAttribute('data-l-'+k)||'';}
	var LNONE=lng('none'), LOPEN=lng('openfull'), LRECIP=lng('recipient'), LDM=lng('dm');

	var body=document.getElementById('jauntymbd-body');
	var compose=document.getElementById('jauntymbd-compose');
	var input=document.getElementById('jauntymbd-input');
	var sendBtn=document.getElementById('jauntymbd-send');
	var userq=document.getElementById('jauntymbd-userq');
	var newRow=dock.querySelector('.jauntymbd-new');
	var fabBadge=document.getElementById('jauntymfab-badge');

	var open=false, view='list', convId=0, timer=null;

	function esc(s){var d=document.createElement('div');d.textContent=(s==null?'':s);return d.innerHTML;}
	function initials(n){n=(n||'?').trim();var p=n.split(/\s+/);return ((p[0]?p[0][0]:'?')+(p[1]?p[1][0]:'')).toUpperCase();}
	function av(html,name){
		if(html&&html.indexOf('<img')!==-1){return '<span class="jauntymbd-av">'+html+'</span>';}
		var h=0;for(var i=0;i<(name||'').length;i++){h=(h+name.charCodeAt(i)*7)%360;}
		return '<span class="jauntymbd-av" style="background:hsl('+h+',42%,55%)">'+esc(initials(name))+'</span>';
	}
	function api(action,params,method){
		method=method||'GET';
		var url=AJAX+(AJAX.indexOf('?')===-1?'?':'&')+'action='+encodeURIComponent(action);
		if(SID){url+='&sid='+encodeURIComponent(SID);}
		var o={method:method,headers:{'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'};
		if(method==='GET'){for(var k in params){if(params.hasOwnProperty(k)){url+='&'+k+'='+encodeURIComponent(params[k]);}}}
		else{var b=[];for(var p in params){if(params.hasOwnProperty(p)){b.push(encodeURIComponent(p)+'='+encodeURIComponent(params[p]));}}o.body=b.join('&');o.headers['Content-Type']='application/x-www-form-urlencoded';}
		return fetch(url,o).then(function(r){return r.json();}).catch(function(){return null;});
	}
	function setBadge(n){
		if(!fabBadge){return;}
		if(n>0){fabBadge.textContent=n>99?'99+':n;fabBadge.style.display='';}
		else{fabBadge.style.display='none';}
	}

	function showList(){
		view='list';convId=0;
		if(compose){compose.style.display='none';}
		if(newRow){newRow.style.display='';}
		api('conversations').then(function(res){
			if(!res||!res.ok||view!=='list'){return;}
			var total=0,html='';
			for(var i=0;i<res.conversations.length;i++){
				var c=res.conversations[i];total+=c.unread;
				html+='<div class="jauntymbd-item'+(c.unread>0?' unread':'')+'" data-conv="'+c.conv_id+'">'
					+av(c.avatar,c.name)
					+'<div class="jauntymbd-meta"><div class="jauntymbd-name">'+esc(c.name)+'</div>'
					+'<div class="jauntymbd-snip">'+(c.snippet_mine?'You: ':'')+esc(c.snippet)+'</div></div>'
					+'<span class="jauntymbd-time">'+esc(c.time)+'</span>'
					+(c.unread>0?'<span class="jauntymbd-dot"></span>':'')
					+'</div>';
			}
			body.innerHTML=html||'<div class="jauntymbd-empty">'+esc(LNONE)+'</div>';
			setBadge(total);
			var items=body.querySelectorAll('[data-conv]');
			for(var n=0;n<items.length;n++){items[n].addEventListener('click',function(){showThread(parseInt(this.getAttribute('data-conv'),10));});}
		});
	}

	function showThread(id){
		view='thread';convId=id;
		if(newRow){newRow.style.display='none';}
		api('messages',{conv_id:id}).then(function(res){
			if(!res||!res.ok||view!=='thread'||convId!==id){return;}
			var p=res.partner||{};
			var html='<div class="jauntymbd-back" id="jauntymbd-back"><i class="icon fa-chevron-left" aria-hidden="true"></i>'
				+av(p.avatar,p.name)+'<span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+esc(p.name||'')+'</span>'
				+'<a href="'+PAGE+(PAGE.indexOf('?')===-1?'?':'&')+'c='+id+'" style="margin-left:auto;font-size:11px;color:#534AB7;text-decoration:none;font-weight:600">'+esc(LOPEN)+'</a></div>'
				+'<div class="jauntymbd-thread">';
			for(var i=0;i<res.messages.length;i++){
				var m=res.messages[i];
				html+='<div class="jauntymbd-line '+(m.mine?'me':'them')+'"><div class="jauntymbd-bub">'+m.html+'</div></div>';
			}
			html+='</div>';
			body.innerHTML=html;
			body.scrollTop=body.scrollHeight;
			if(compose&&CANDM){compose.style.display='flex';}
			document.getElementById('jauntymbd-back').addEventListener('click',function(e){
				if(e.target.tagName==='A'){return;}
				showList();
			});
		});
	}

	function send(){
		if(!input){return;}
		var t=input.value.replace(/\s+$/,'');
		if(!t||!convId){return;}
		sendBtn.disabled=true;
		api('send',{text:t,hash:TOKEN,conv_id:convId},'POST').then(function(res){
			sendBtn.disabled=false;
			if(!res||!res.ok){if(res&&res.error){alert(res.error);}return;}
			input.value='';
			showThread(convId);
		});
	}

	function searchUsers(q){
		view='search';
		if(compose){compose.style.display='none';}
		api('search_users',{q:q}).then(function(res){
			if(!res||!res.ok||view!=='search'){return;}
			var html='';
			for(var i=0;i<res.users.length;i++){
				var u=res.users[i];
				html+='<div class="jauntymbd-item" data-uid="'+u.user_id+'">'+av(u.avatar,u.name)
					+'<div class="jauntymbd-meta"><div class="jauntymbd-name">'+esc(u.name)+'</div></div></div>';
			}
			body.innerHTML=html||'<div class="jauntymbd-empty">'+esc(LRECIP)+'</div>';
			var items=body.querySelectorAll('[data-uid]');
			for(var n=0;n<items.length;n++){items[n].addEventListener('click',function(){startWith(parseInt(this.getAttribute('data-uid'),10));});}
		});
	}

	function startWith(uid){
		api('start',{user_id:uid}).then(function(res){
			if(!res||!res.ok){if(res&&res.error){alert(res.error);}return;}
			if(userq){userq.value='';}
			openDock();
			showThread(res.conv_id);
			if(input){input.focus();}
		});
	}

	function tick(){
		if(!open){return;}
		if(view==='list'){showList();}
		else if(view==='thread'&&convId){showThread(convId);}
	}

	function openDock(){
		if(!open){
			open=true;dock.style.display='flex';showList();
			if(timer){clearInterval(timer);}
			timer=setInterval(tick,POLL);
		}
	}
	function closeDock(){
		open=false;dock.style.display='none';
		if(timer){clearInterval(timer);timer=null;}
	}

	fab.addEventListener('click',function(){
		if(open){closeDock();}else{openDock();}
	});
	document.getElementById('jauntymbd-close').addEventListener('click',closeDock);
	if(sendBtn){sendBtn.addEventListener('click',send);}
	if(input){input.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();send();}});}
	if(userq){
		var t;
		userq.addEventListener('input',function(){
			clearTimeout(t);var v=this.value;
			t=setTimeout(function(){
				if(v&&v.trim().length>=2){searchUsers(v);}
				else if(view==='search'){showList();}
			},220);
		});
	}
	window.addEventListener('focus',function(){if(open){tick();}});

	/* hover "Message" button on usernames */
	if(CANDM){
		var btn=null, hideT=null;
		function makeBtn(){
			btn=document.createElement('button');
			btn.type='button';btn.className='jauntymdm-btn';btn.style.display='none';
			btn.innerHTML='<i class="icon fa-comment-o" aria-hidden="true"></i> '+esc(LDM);
			document.body.appendChild(btn);
			btn.addEventListener('mouseenter',function(){if(hideT){clearTimeout(hideT);}});
			btn.addEventListener('mouseleave',scheduleHide);
			btn.addEventListener('click',function(){
				var uid=parseInt(btn.getAttribute('data-uid'),10);
				btn.style.display='none';
				if(uid){startWith(uid);}
			});
		}
		function scheduleHide(){hideT=setTimeout(function(){if(btn){btn.style.display='none';}},350);}
		function uidFrom(a){
			var m=(a.getAttribute('href')||'').match(/[?&;]u=(\d+)/);
			return m?parseInt(m[1],10):0;
		}
		document.addEventListener('mouseover',function(e){
			var a=(e.target&&e.target.closest)?e.target.closest('a.username, a.username-coloured'):null;
			if(!a){return;}
			var uid=uidFrom(a);
			if(!uid||uid===ME){return;}
			if(!btn){makeBtn();}
			if(hideT){clearTimeout(hideT);}
			var r=a.getBoundingClientRect();
			btn.setAttribute('data-uid',uid);
			btn.style.left=(window.scrollX+r.left)+'px';
			btn.style.top=(window.scrollY+r.bottom+4)+'px';
			btn.style.display='';
		});
		document.addEventListener('mouseout',function(e){
			var a=(e.target&&e.target.closest)?e.target.closest('a.username, a.username-coloured'):null;
			if(a){scheduleHide();}
		});
	}
})();
