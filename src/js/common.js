const API = '/api';
const LOGIN_PAGE = 'index.html';

const getToken = () => localStorage.getItem('token');
const getUsuario = () => JSON.parse(localStorage.getItem('usuario') || 'null');

function api(method, url, data) {
  return $.ajax({
    url: API + url, method, contentType: 'application/json', dataType: 'json',
    data: data !== undefined ? JSON.stringify(data) : undefined,
    headers: getToken() ? { Authorization: 'Bearer ' + getToken() } : {}
  });
}
const erroMsg = xhr => (xhr.responseJSON && xhr.responseJSON.erro) || 'Erro inesperado. Tente novamente.';

function logout() {
  localStorage.removeItem('token');
  localStorage.removeItem('usuario');
  sessionStorage.removeItem('avisado');
  location.href = LOGIN_PAGE;
}
function exigirLogin() { if (!getToken()) location.href = LOGIN_PAGE; }

const esc = s => $('<div>').text(s == null ? '' : s).html();
function fmtData(s) {
  if (!s) return '';
  const [a, m, d] = s.substring(0, 10).split('-');
  return `${d}/${m}/${a}`;
}

// Token expirado/inválido em qualquer página protegida -> volta ao login
$(document).ajaxError((e, xhr) => { if (xhr.status === 401 && getToken()) logout(); });
