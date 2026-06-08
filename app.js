/* ===================================
   EUGENVERSE APP.JS PRO EDITION
   Rapi, aman, tidak seperti tugas mepet.
=================================== */

/* =========================
   GLOBAL
========================= */

const APP = {
baseUrl: "",
chatTimer: null
};

function qs(id){
return document.getElementById(id);
}

function qsa(sel){
return document.querySelectorAll(sel);
}

function esc(str){
if(str === null || str === undefined) return "";
return String(str)
.replace(/&/g,"&amp;")
.replace(/</g,"&lt;")
.replace(/>/g,"&gt;")
.replace(/"/g,"&quot;")
.replace(/'/g,"&#039;");
}

function toast(msg,type="info"){

let old = document.querySelector(".toast-box");
if(old) old.remove();

let box = document.createElement("div");
box.className = "toast-box " + type;
box.innerText = msg;

document.body.appendChild(box);

setTimeout(()=>{
box.classList.add("show");
},50);

setTimeout(()=>{
box.classList.remove("show");
setTimeout(()=>box.remove(),300);
},2200);
}

async function post(url,data={}){

const body = new URLSearchParams(data).toString();

const res = await fetch(APP.baseUrl + url,{
method:"POST",
headers:{
"Content-Type":"application/x-www-form-urlencoded"
},
body
});

return res;
}

/* =========================
   FAVORITE
========================= */

async function addFav(title){

try{

await post("favorite.php",{
judul:title
});

toast("Masuk favorit. Manusia suka mengoleksi segalanya.","success");

}catch(err){

console.log(err);
toast("Gagal tambah favorit","error");

}
}

/* =========================
   LIBRARY FAVORITE
========================= */

async function loadLibrary(){

const favList = qs("favList");
if(!favList) return;

try{

const res = await fetch("get_favorite.php");
const data = await res.json();

if(!Array.isArray(data) || data.length < 1){

favList.innerHTML = `
<div class="empty-box">
Belum ada favorit. Hampa tapi konsisten.
</div>
`;

return;
}

favList.innerHTML = data.map(item => `
<a href="detail.php?id=${item.id}" class="card">
<img src="uploads/cover/${esc(item.cover)}">
<h3>${esc(item.judul)}</h3>
<div class="chap">Chapter ${esc(item.chapter)}</div>
</a>
`).join("");

}catch(err){

console.log(err);

}
}

/* =========================
   HISTORY
========================= */

async function loadHistory(){

const box = qs("historyList");
if(!box) return;

try{

const res = await fetch("get_history.php");
const data = await res.json();

if(!Array.isArray(data) || data.length < 1){

box.innerHTML = `
<div class="empty-box">
Belum ada riwayat baca.
</div>
`;

return;
}

box.innerHTML = data.map(item => `
<a href="reader.php?id=${item.chapter_id}" class="card">
<img src="uploads/cover/${esc(item.cover)}">
<h3>${esc(item.judul)}</h3>
<div class="chap">
Terakhir baca Ch ${esc(item.chapter)}
</div>
</a>
`).join("");

}catch(err){

console.log(err);

}
}

/* =========================
   CHAT
========================= */

async function sendMsg(){

const msg = qs("msg");
if(!msg) return;

const text = msg.value.trim();

if(text === "") return;

try{

await post("chat_send.php",{
message:text
});

msg.value = "";
loadChat();

}catch(err){

console.log(err);

}
}

async function loadChat(){

const box = qs("chatBox");
if(!box) return;

try{

const res = await fetch("chat_load.php");
const data = await res.json();

if(!Array.isArray(data)) return;

box.innerHTML = data.map(item => `
<div class="chat-message">
<div class="chat-user">${esc(item.username)}</div>
<div class="chat-text">${esc(item.pesan)}</div>
<div class="chat-time">${esc(item.waktu)}</div>
</div>
`).join("");

box.scrollTop = box.scrollHeight;

}catch(err){

console.log(err);

}
}

function initChat(){

if(!qs("chatBox")) return;

loadChat();

APP.chatTimer = setInterval(()=>{
loadChat();
},3000);
}

/* =========================
   DOWNLOAD
========================= */

async function addDownload(){

const manual = qs("manual");
if(!manual) return;

const title = manual.value.trim();

if(title === "") return;

try{

await post("download_add.php",{
judul:title
});

manual.value = "";
loadDownload();

}catch(err){

console.log(err);

}
}

async function loadDownload(){

const list = qs("downloadList");
if(!list) return;

try{

const res = await fetch("download_load.php");
const data = await res.json();

if(!Array.isArray(data) || data.length < 1){

list.innerHTML = `
<div class="empty-box">
Belum ada komik offline.
</div>
`;

return;
}

list.innerHTML = data.map(item => `
<div class="card">
<img src="uploads/cover/${esc(item.cover)}">
<h3>${esc(item.judul)}</h3>
<div class="chap">${esc(item.chapter)}</div>
</div>
`).join("");

}catch(err){

console.log(err);

}
}

/* =========================
   SETTING
========================= */

async function saveSetting(){

const mode = qs("mode");
const theme = qs("theme");

if(!mode) return;

try{

await post("save_setting.php",{
mode:mode.value,
theme:theme ? theme.value : "dark"
});

toast("Pengaturan disimpan","success");

}catch(err){

console.log(err);
toast("Gagal menyimpan","error");

}
}

/* =========================
   API MANGADEX OPTIONAL
========================= */

async function loadManga(){

const container = qs("exploreList");
if(!container) return;

try{

const res = await fetch("https://api.mangadex.org/manga?limit=8");
const json = await res.json();

if(!json.data) return;

container.innerHTML = json.data.map(item=>{

let title =
item.attributes.title.en ||
Object.values(item.attributes.title)[0] ||
"No Title";

title = esc(title);

return `
<div class="card">
<img src="https://placehold.co/300x420?text=Manga">
<h3>${title}</h3>
<div class="chap">Mangadex API</div>
<button class="main-btn"
onclick="addFav('${title}')">
♡ Favorit
</button>
</div>
`;

}).join("");

}catch(err){

console.log(err);

}
}

/* =========================
   FIREBASE LOGIN OPTIONAL
========================= */

function loginGoogle(){

if(typeof firebase === "undefined"){
toast("Firebase belum aktif","error");
return;
}

const provider = new firebase.auth.GoogleAuthProvider();

firebase.auth()
.signInWithPopup(provider)
.then(()=>{
window.location.href = "index.php";
})
.catch(err=>{
console.log(err);
});
}

function initAvatar(){

if(typeof firebase === "undefined") return;

firebase.auth().onAuthStateChanged(user=>{

const avatar = qs("avatar");
if(!avatar) return;

if(user){

avatar.style.backgroundSize = "cover";
avatar.style.backgroundPosition = "center";

avatar.style.backgroundImage = user.photoURL
? `url(${user.photoURL})`
: `url(https://i.pravatar.cc/100)`;

}

});
}

/* =========================
   INIT
========================= */

document.addEventListener("DOMContentLoaded",()=>{

loadLibrary();
loadHistory();
loadDownload();
initChat();
initAvatar();

/* kalau explore kosong pakai API */
if(qs("exploreList") && qs("exploreList").children.length === 0){
loadManga();
}

});

/* =========================
   ENTER CHAT
========================= */

document.addEventListener("keydown",(e)=>{

if(e.key === "Enter" && document.activeElement === qs("msg")){
sendMsg();
}

});