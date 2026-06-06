
(function(){var b=document.body;if(!b||!b.dataset)return;var map={theme:'--theme',bg:'--bg',text:'--text',chip:'--chip',size:'--size'};Object.keys(map).forEach(function(k){if(b.dataset[k])document.documentElement.style.setProperty(map[k],b.dataset[k]);});})();
