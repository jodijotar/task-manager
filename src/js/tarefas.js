exigirLogin();

const ROTULO = { em_aberto: 'Em aberto', concluida: 'Concluída', em_atraso: 'Em atraso' };
const BADGE  = { em_aberto: 'text-bg-light border', concluida: 'text-bg-secondary', em_atraso: 'text-bg-dark' };
let tarefas = [];

const u = getUsuario();
if (u) {
  $('#usuarioNome').text(u.nome + ' ' + u.sobrenome + (u.admin ? ' (admin)' : ''));
  if (u.foto) $('#avatarUsuario').attr('src', '/' + u.foto).removeClass('d-none');
}

function carregar() {
  const termo = $('#busca').val().trim();
  const url = termo ? '/tarefas?busca=' + encodeURIComponent(termo) : '/tarefas';
  return api('GET', url)
    .done(lista => { tarefas = lista; render(); avisarAtraso(); })
    .fail(x => alert(erroMsg(x)));
}

let buscaTimer;
$('#busca').on('input', function () {
  clearTimeout(buscaTimer);
  buscaTimer = setTimeout(carregar, 300);
});

function render() {
  const $l = $('#lista').empty();
  if (!tarefas.length) return $l.append('<tr><td colspan="6" class="text-muted">Nenhuma atividade cadastrada.</td></tr>');
  tarefas.forEach(t => {
    const feita = t.status === 'concluida';
    $l.append(`<tr data-id="${t.id}" class="${feita ? 'text-muted' : ''}">
      <td class="${feita ? 'text-decoration-line-through' : ''}">${esc(t.titulo)}</td>
      <td>${esc(t.descricao)}</td>
      <td>${fmtData(t.data_criacao)}</td>
      <td>${fmtData(t.data_conclusao)}</td>
      <td><span class="badge ${BADGE[t.status]}">${ROTULO[t.status]}</span></td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-outline-dark act-concluir" ${feita ? 'disabled' : ''}>Concluir</button>
        <button class="btn btn-sm btn-outline-dark act-editar">Editar</button>
        <button class="btn btn-sm btn-outline-dark act-excluir">Excluir</button>
      </td></tr>`);
  });
}

// Banner fixo + aviso em modal uma vez por login
function avisarAtraso() {
  const n = tarefas.filter(t => t.status === 'em_atraso').length;
  const $b = $('#bannerAtraso');
  if (!n) return $b.addClass('d-none');
  const txt = n === 1 ? 'Você tem 1 atividade em atraso.' : `Você tem ${n} atividades em atraso.`;
  $b.text(txt).removeClass('d-none');
  if (!sessionStorage.getItem('avisado')) {
    sessionStorage.setItem('avisado', '1');
    $('#textoAviso').text(txt);
    new bootstrap.Modal('#modalAviso').show();
  }
}

const modal = new bootstrap.Modal('#modalTarefa');
const $form = $('#formTarefa');

function abrirForm(t) {
  $form[0].reset();
  $('#msgTarefa').addClass('d-none');
  $form.find('[name=id]').val(t ? t.id : '');
  $('#tituloModal').text(t ? 'Editar atividade' : 'Nova atividade');
  $('#boxCriacao').toggle(!!t);
  if (t) {
    $form.find('[name=titulo]').val(t.titulo);
    $form.find('[name=descricao]').val(t.descricao || '');
    $form.find('[name=data_conclusao]').val(t.data_conclusao);
    $form.find('[name=status]').val(t.status === 'concluida' ? 'concluida' : 'em_aberto');
    $('#dataCriacao').val(fmtData(t.data_criacao));
  }
  modal.show();
}

$('#btnNova').on('click', () => abrirForm(null));
$('#btnSair').on('click', logout);

// ---- Foto de perfil ----
const TIPOS_AVATAR = ['image/jpeg', 'image/png', 'image/gif'];
const $msgFoto = $('#msgFoto');
const modalFoto = new bootstrap.Modal('#modalFoto');

$('#btnFoto').on('click', () => { $msgFoto.addClass('d-none'); $('#formFoto')[0].reset(); modalFoto.show(); });

$('#formFoto').on('submit', function (e) {
  e.preventDefault();
  const arquivo = $('#inputFoto')[0].files[0];
  if (!arquivo) return;

  // Validação feita no navegador, a partir do "accept" do <input> e do MIME type
  // que o próprio navegador reporta para o arquivo escolhido. Isso é só uma
  // conveniência de UX: qualquer requisição feita fora do formulário (curl, Burp,
  // extensão do navegador) pode enviar qualquer Content-Type e qualquer conteúdo,
  // pois essa checagem nunca chega a rodar no servidor.
  if (!TIPOS_AVATAR.includes(arquivo.type)) {
    return msg($msgFoto, 'Selecione um arquivo de imagem (jpg, png ou gif).');
  }
  if (arquivo.size > 2 * 1024 * 1024) {
    return msg($msgFoto, 'Arquivo muito grande (máx. 2MB).');
  }

  const fd = new FormData();
  fd.append('foto', arquivo);
  $.ajax({
    url: API + '/perfil/foto', method: 'POST', data: fd, processData: false, contentType: false,
    headers: getToken() ? { Authorization: 'Bearer ' + getToken() } : {}
  }).done(r => {
    const usuario = getUsuario();
    usuario.foto = r.foto;
    localStorage.setItem('usuario', JSON.stringify(usuario));
    $('#avatarUsuario').attr('src', '/' + r.foto + '?t=' + Date.now()).removeClass('d-none');
    modalFoto.hide();
  }).fail(x => msg($msgFoto, erroMsg(x)));
});

$form.on('submit', function (e) {
  e.preventDefault();
  const d = Object.fromEntries(new FormData(this).entries());
  const id = d.id;
  const req = id ? api('PUT', '/tarefas/' + id, d) : api('POST', '/tarefas', d);
  req.done(() => { modal.hide(); carregar(); })
     .fail(x => $('#msgTarefa').text(erroMsg(x)).removeClass('d-none'));
});

const idDaLinha = el => +$(el).closest('tr').data('id');

$('#lista').on('click', '.act-editar', function () {
  abrirForm(tarefas.find(t => t.id === idDaLinha(this)));
});
$('#lista').on('click', '.act-concluir', function () {
  api('PUT', `/tarefas/${idDaLinha(this)}/concluir`).done(carregar).fail(x => alert(erroMsg(x)));
});
$('#lista').on('click', '.act-excluir', function () {
  if (!confirm('Excluir esta atividade?')) return;
  api('DELETE', '/tarefas/' + idDaLinha(this)).done(carregar).fail(x => alert(erroMsg(x)));
});

carregar();
