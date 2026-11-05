/**
 * RESIK COLLECT — Monitoring Retribusi Persampahan
 * Dinas Lingkungan Hidup Kabupaten Pringsewu
 * Penyimpanan: Google Spreadsheet (lembar ini). Pembayaran: QRIS Bank Lampung.
 */
var TBL = ['petugas','wilayah','pos','pelanggan','bayar','mutasi','cover','notes','audit','settings'];
var KEYF = {pos:'kode', pelanggan:'kode'};
var TZ = 'Asia/Jakarta';
var SESI_HARI = 30;

function ss_(){ return SpreadsheetApp.getActive(); }
function sh_(n){ var s = ss_().getSheetByName(n); return s; }
function shMust_(n){ var s = sh_(n); if(!s){ s = ss_().insertSheet(n); } return s; }
function keyOf_(t, row){ return String(row[KEYF[t] || 'id'] || ''); }
function hariIni_(){ return Utilities.formatDate(new Date(), TZ, 'yyyy-MM-dd'); }
function terpasang_(){ return !!sh_('_meta'); }

/* ---------- penghitung perubahan ---------- */
function ubNext_(n){
  var m = shMust_('_meta');
  var cur = Number(m.getRange('B1').getValue() || 0);
  var next = cur + (n || 1);
  m.getRange('A1').setValue('ub'); m.getRange('B1').setValue(next);
  return next;
}
function ubNow_(){ var m = sh_('_meta'); return m ? Number(m.getRange('B1').getValue() || 0) : 0; }

/* ---------- baca / tulis tabel ---------- */
function baca_(t, since){
  var s = sh_(t); if(!s) return [];
  var last = s.getLastRow(); if(last < 2) return [];
  var v = s.getRange(2, 1, last - 1, 3).getValues();
  var out = [];
  for(var i = 0; i < v.length; i++){
    if(!v[i][1]) continue;
    if(since && Number(v[i][2] || 0) <= since) continue;
    try{ out.push(JSON.parse(v[i][1])); }catch(e){}
  }
  return out;
}
function tulis_(t, rows, ub){
  if(!rows.length) return;
  var s = shMust_(t);
  var last = s.getLastRow();
  var keys = last > 1 ? s.getRange(2, 1, last - 1, 1).getValues() : [];
  var idx = {};
  for(var i = 0; i < keys.length; i++) idx[String(keys[i][0])] = i + 2;
  var tambah = [];
  for(var j = 0; j < rows.length; j++){
    var k = keyOf_(t, rows[j]);
    var line = [k, JSON.stringify(rows[j]), ub];
    if(idx[k]) s.getRange(idx[k], 1, 1, 3).setValues([line]);
    else { tambah.push(line); idx[k] = last + tambah.length; }
  }
  if(tambah.length) s.getRange(s.getLastRow() + 1, 1, tambah.length, 3).setValues(tambah);
}

/* ---------- pengguna & sesi ---------- */
function hash_(pin, salt){
  var b = Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, String(salt) + '|' + String(pin), Utilities.Charset.UTF_8);
  return b.map(function(x){ return ('0' + (x & 0xFF).toString(16)).slice(-2); }).join('');
}
function users_(){ return baca_('pengguna'); }
function userById_(id){ var u = users_(); for(var i = 0; i < u.length; i++) if(u[i].id === id) return u[i]; return null; }
function simpanUser_(u){ tulis_('pengguna', [u], ubNext_()); }

function props_(){ return PropertiesService.getScriptProperties(); }
function buatSesi_(id){
  var tok = Utilities.getUuid().replace(/-/g, '');
  var exp = Date.now() + SESI_HARI * 864e5;
  props_().setProperty('s:' + tok, id + '|' + exp);
  return tok;
}
function sesi_(tok){
  if(!tok) return null;
  var v = props_().getProperty('s:' + tok);
  if(!v) return null;
  var p = v.split('|');
  if(Number(p[1]) < Date.now()){ props_().deleteProperty('s:' + tok); return null; }
  return userById_(p[0]);
}
function hapusSesi_(tok){ if(tok) props_().deleteProperty('s:' + tok); }

/* ---------- halaman ---------- */
function doGet(){
  if(!terpasang_()) pasang();
  return HtmlService.createHtmlOutputFromFile('index')
    .setTitle('Resik Collect — DLH Pringsewu')
    .addMetaTag('viewport', 'width=device-width, initial-scale=1, viewport-fit=cover')
    .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
}

/* ---------- jembatan dari halaman ---------- */
function rpc(a, bodyStr, q, tok){
  try{
    if(!terpasang_()) pasang();
    var body = bodyStr ? JSON.parse(bodyStr) : null;
    var since = 0;
    var m = /since=(\d+)/.exec(q || ''); if(m) since = Number(m[1]);

    if(a === 'pengguna'){
      var daftar = users_().filter(function(u){ return u.aktif !== false; })
        .map(function(u){ return {id: u.id, nama: u.nama, peran: u.peran, ptg: u.ptg || ''}; });
      return JSON.stringify({ok: true, pengguna: daftar});
    }
    if(a === 'masuk'){
      var u = userById_(body && body.id);
      if(!u || u.aktif === false) return tolak_('Pengguna tidak ditemukan atau tidak aktif.', 400);
      if(!u.pin) return tolak_('PIN belum dibuat. Minta Admin/Kadis membuat PIN.', 400);
      if(hash_(body.pin, u.salt) !== u.pin) return tolak_('PIN salah.', 400);
      return JSON.stringify({ok: true, token: buatSesi_(u.id)});
    }
    if(a === 'keluar'){ hapusSesi_(tok); return JSON.stringify({ok: true}); }

    var me = sesi_(tok);
    if(!me) return tolak_('Sesi berakhir. Silakan masuk lagi.', 401, true);

    if(a === 'data'){
      var rows = {};
      TBL.forEach(function(t){ rows[t] = baca_(t, since); });
      return JSON.stringify({ok: true, me: {id: me.id, nama: me.nama, peran: me.peran, ptg: me.ptg || ''},
        ub: ubNow_(), today: hariIni_(), rows: rows});
    }
    if(a === 'cadangan'){
      if(me.peran !== 'kadis' && me.peran !== 'bendahara') return tolak_('Tidak punya hak.', 403);
      var all = {};
      TBL.forEach(function(t){ all[t] = baca_(t); });
      all.diambil = new Date().toISOString();
      return JSON.stringify({ok: true, data: all});
    }
    if(a === 'simpan'){
      if(me.peran === 'monev') return tolak_('Peran Monev hanya boleh melihat.', 403);
      var ops = (body && body.ops) || [];
      if(!ops.length) return JSON.stringify({ok: true, rows: []});
      var lock = LockService.getScriptLock();
      lock.waitLock(25000);
      try{
        var ub = ubNext_();
        var byT = {}, hasil = [];
        ops.forEach(function(o){
          if(TBL.indexOf(o.t) < 0) return;
          (byT[o.t] = byT[o.t] || []).push(o.row);
          hasil.push({t: o.t, row: o.row, asal: keyOf_(o.t, o.row)});
        });
        Object.keys(byT).forEach(function(t){ tulis_(t, byT[t], ub); });
      } finally { lock.releaseLock(); }
      return JSON.stringify({ok: true, rows: hasil});
    }
    if(a === 'ganti_pin'){
      var me2 = userById_(me.id);
      if(!me2.pin || hash_(body.lama, me2.salt) !== me2.pin) return tolak_('PIN lama salah.', 400);
      if(!/^\d{6}$/.test(String(body.baru))) return tolak_('PIN baru harus 6 angka.', 400);
      me2.salt = Utilities.getUuid(); me2.pin = hash_(body.baru, me2.salt);
      simpanUser_(me2);
      return JSON.stringify({ok: true});
    }
    if(a === 'kelola' || a === 'kelola_simpan'){
      if(me.peran !== 'kadis') return tolak_('Hanya Admin/Kadis.', 403);
      if(a === 'kelola'){
        var ls = users_().map(function(u){ return {id: u.id, nama: u.nama, peran: u.peran, ptg: u.ptg || '', aktif: u.aktif !== false, adaPin: !!u.pin}; });
        return JSON.stringify({ok: true, pengguna: ls});
      }
      var id = body.id || ('U' + Date.now().toString(36).toUpperCase());
      var ex = userById_(id) || {id: id, salt: Utilities.getUuid(), pin: ''};
      ex.nama = String(body.nama || '').trim();
      if(!ex.nama) return tolak_('Isi nama pengguna.', 400);
      ex.peran = body.peran; ex.ptg = body.peran === 'petugas' ? (body.ptg || '') : '';
      ex.aktif = !!body.aktif;
      if(body.pin){
        if(!/^\d{6}$/.test(String(body.pin))) return tolak_('PIN harus 6 angka.', 400);
        ex.salt = Utilities.getUuid(); ex.pin = hash_(body.pin, ex.salt);
      }
      simpanUser_(ex);
      return JSON.stringify({ok: true});
    }
    return tolak_('Perintah tidak dikenal.', 404);
  }catch(err){
    return JSON.stringify({ok: false, status: 500, pesan: 'Server: ' + (err && err.message ? err.message : err)});
  }
}
function tolak_(pesan, status, keluar){ return JSON.stringify({ok: false, pesan: pesan, status: status || 400, keluar: !!keluar}); }

/* ---------- pemasangan sekali jalan ---------- */
function pasang(){
  var s = ss_();
  shMust_('_meta').getRange('A1:B1').setValues([['ub', 1]]);
  var semua = TBL.concat(['pengguna']);
  semua.forEach(function(t){
    var sh = sh_(t);
    if(!sh){ sh = s.insertSheet(t); sh.getRange('A1:C1').setValues([['kunci', 'data (JSON)', 'ub']]); sh.setFrozenRows(1); }
  });
  var ub = 1;
  TBL.forEach(function(t){
    if(baca_(t).length) return;
    var rows = (typeof RESIK_SEED !== 'undefined' && RESIK_SEED[t]) ? RESIK_SEED[t] : [];
    if(rows.length) tulis_(t, rows, ub);
  });
  if(!users_().length) buatPengguna_();
  var s1 = s.getSheetByName('Sheet1');
  if(s1 && s1.getLastRow() === 0 && s.getSheets().length > 1) s.deleteSheet(s1);
  return 'Pemasangan selesai.';
}

function pin6_(){ return String(Math.floor(100000 + Math.random() * 900000)); }
function buatPengguna_(){
  var daftar = [];
  var dasar = [
    {id: 'ADM', nama: 'Admin / Kadis', peran: 'kadis'},
    {id: 'BDH', nama: 'Bendahara', peran: 'bendahara'},
    {id: 'MNV', nama: 'Monev', peran: 'monev'}
  ];
  var ptgs = (typeof RESIK_SEED !== 'undefined' && RESIK_SEED.petugas) ? RESIK_SEED.petugas : [];
  ptgs.forEach(function(k){
    if(k.jenis === 'langsung') return;
    dasar.push({id: 'U' + k.id, nama: k.lengkap || k.nama, peran: 'petugas', ptg: k.id});
  });
  var catat = [['ID', 'Nama', 'Peran', 'PIN awal (ganti setelah dibagikan)']];
  var baris = [];
  dasar.forEach(function(d){
    var pin = pin6_();
    var salt = Utilities.getUuid();
    baris.push({id: d.id, nama: d.nama, peran: d.peran, ptg: d.ptg || '', aktif: true, salt: salt, pin: hash_(pin, salt)});
    catat.push([d.id, d.nama, d.peran, pin]);
  });
  tulis_('pengguna', baris, ubNext_());
  var sh = shMust_('PIN awal');
  sh.clear();
  sh.getRange(1, 1, catat.length, 4).setValues(catat);
  sh.setFrozenRows(1);
  sh.getRange(1, 1, 1, 4).setFontWeight('bold');
  sh.setColumnWidth(2, 220);
}


