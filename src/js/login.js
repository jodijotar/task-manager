if (getToken()) location.href = 'tarefas.html';

const dados = $f => Object.fromEntries(new FormData($f[0]).entries());

$('#formLogin').on('submit', function (e) {
  e.preventDefault();
  api('POST', '/login', dados($(this)))
    .done(r => {
      localStorage.setItem('token', r.token);
      localStorage.setItem('usuario', JSON.stringify(r.usuario));
      sessionStorage.removeItem('avisado'); // permite o aviso de atraso após o login
      location.href = 'tarefas.html';
    })
    .fail(x => msg($('#msgLogin'), erroMsg(x)));
});

$('#formUsuario').on('submit', function (e) {
  e.preventDefault();
  api('POST', '/usuarios', dados($(this)))
    .done(() => {
      this.reset();
      bootstrap.Modal.getInstance('#modalUsuario').hide();
      msg($('#msgLogin'), 'Usuário cadastrado! Faça login.');
    })
    .fail(x => msg($('#msgUsuario'), erroMsg(x)));
});
