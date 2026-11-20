/**
 * Termos de uso e política de privacidade (LGPD). Escritos a partir do que o sistema faz de fato;
 * revise com o orientador antes de publicar. Ao mudar o texto, atualize VERSAO e também
 * beready.termos_versao no backend (config/beready.php), que é a versão gravada no aceite.
 *
 * Só em português e inglês: os outros idiomas mostram a versão em inglês com um aviso.
 */
export const VERSAO = '2026-11-20'

export interface Secao {
  titulo: string
  paragrafos: string[]
}

export interface TextoLegal {
  termos: Secao[]
  privacidade: Secao[]
}

// {contato} é trocado pelo e-mail de contato (VITE_CONTATO_EMAIL) ou pelo repositório do projeto
export const CONTEUDO: Record<'pt' | 'en', TextoLegal> = {
  pt: {
    termos: [
      {
        titulo: '1. Sobre o BeReady',
        paragrafos: [
          'O BeReady é uma plataforma de estudo de inglês desenvolvida como projeto acadêmico (trabalho de conclusão de curso). Ao criar uma conta ou entrar com uma rede social, você concorda com estes termos e com a política de privacidade.',
        ],
      },
      {
        titulo: '2. Sua conta',
        paragrafos: [
          'Você precisa confirmar seu e-mail para usar a conta e é responsável por manter sua senha em segredo. Use dados verdadeiros e não crie contas em nome de outras pessoas.',
          'Você pode editar seus dados no perfil, exportar tudo o que guardamos sobre você e excluir a conta quando quiser.',
        ],
      },
      {
        titulo: '3. Uso permitido',
        paragrafos: [
          'O BeReady serve para estudo pessoal. Não é permitido usar a plataforma para enviar conteúdo ilegal, ofensivo ou que viole direitos de terceiros, tentar acessar dados de outros usuários, nem automatizar acessos para sobrecarregar o serviço.',
          'Podemos suspender contas que descumpram estas regras.',
        ],
      },
      {
        titulo: '4. Conteúdo gerado por inteligência artificial',
        paragrafos: [
          'Traduções e frases semelhantes podem ser geradas por um modelo de IA (Claude, da Anthropic). O resultado pode conter erros: use-o como apoio ao estudo, não como fonte definitiva. Há um limite diário de gerações por usuário.',
        ],
      },
      {
        titulo: '5. Planos e pagamentos',
        paragrafos: [
          'O plano gratuito tem limites de uso. O plano pago é uma assinatura recorrente (mensal ou anual) processada pelo Stripe; o BeReady não recebe nem guarda os dados do seu cartão. Você pode cancelar a qualquer momento: o acesso pago continua até o fim do período já cobrado.',
        ],
      },
      {
        titulo: '6. Disponibilidade',
        paragrafos: [
          'Por ser um projeto acadêmico, o serviço é oferecido "no estado em que se encontra", sem garantia de funcionamento contínuo. Podemos mudar funcionalidades ou encerrar o serviço avisando com antecedência pelo e-mail cadastrado.',
        ],
      },
      {
        titulo: '7. Alterações e contato',
        paragrafos: [
          'Se estes termos mudarem, a nova versão será publicada nesta página com a data de atualização, e pediremos um novo aceite quando a mudança for relevante.',
          'Dúvidas: {contato}.',
        ],
      },
    ],
    privacidade: [
      {
        titulo: '1. Quem trata os seus dados',
        paragrafos: [
          'Esta política explica quais dados pessoais o BeReady guarda, para quê e com quem são compartilhados, conforme a Lei Geral de Proteção de Dados (Lei 13.709/2018). O BeReady é um projeto acadêmico; o contato para assuntos de privacidade é {contato}.',
        ],
      },
      {
        titulo: '2. Dados que coletamos',
        paragrafos: [
          'Cadastro e perfil: nome, e-mail, senha (guardada só em forma de hash, nunca em texto), telefone (opcional), foto de perfil (opcional), nível de inglês, idioma preferido e objetivos de estudo.',
          'Conteúdo de estudo: flashcards, tags, quizzes, prompts, traduções, frases e imagens que você cria ou gera.',
          'Uso da plataforma: respostas às revisões e quizzes, progresso, sequência de dias, tempo de estudo, notas do exercício de pronúncia (só o texto reconhecido, não o áudio), conquistas, resultado do teste de nivelamento, preferências e o registro de aceite destes termos.',
          'Assinatura: plano, datas e situação do pagamento. Os dados do cartão ficam apenas com o Stripe.',
        ],
      },
      {
        titulo: '3. Para que usamos',
        paragrafos: [
          'Para criar e manter sua conta, mostrar seu progresso, adaptar o conteúdo ao seu nível, cobrar e controlar a assinatura, enviar e-mails do serviço (confirmação de e-mail, recuperação de senha e lembretes de estudo, que podem ser desligados nas preferências) e proteger a plataforma contra abuso.',
          'Bases legais: execução do contrato (prestar o serviço que você pediu), cumprimento de obrigação legal e, para os lembretes por e-mail e o login social, o seu consentimento, que pode ser revogado a qualquer momento.',
        ],
      },
      {
        titulo: '4. Com quem compartilhamos',
        paragrafos: [
          'Stripe: processamento dos pagamentos da assinatura.',
          'Anthropic (Claude): o texto do prompt quando você pede uma tradução ou frases geradas por IA.',
          'Cloudinary: armazenamento da foto de perfil.',
          'Google, Facebook e LinkedIn: apenas se você escolher entrar com essas contas; recebemos nome, e-mail e foto.',
          'Google reCAPTCHA: proteção do login e do cadastro contra robôs.',
          'Reconhecimento de voz do navegador: no exercício de pronúncia, o navegador (Google no Chrome, Microsoft no Edge) pode enviar o áudio aos servidores dele para transformar em texto. O BeReady recebe só o texto.',
          'Hospedagem do banco de dados, envio de e-mails e monitoramento de erros, contratados para operar o serviço.',
          'Não vendemos seus dados nem os usamos para publicidade.',
        ],
      },
      {
        titulo: '5. Cookies e armazenamento no navegador',
        paragrafos: [
          'Usamos um cookie técnico, protegido (httpOnly), para manter você conectado com segurança, e guardamos no navegador o idioma escolhido. Não usamos cookies de rastreamento nem de publicidade.',
        ],
      },
      {
        titulo: '6. Por quanto tempo guardamos',
        paragrafos: [
          'Enquanto a sua conta existir. Ao excluir a conta, apagamos o perfil e todo o conteúdo de estudo e de uso ligado a ela, e cancelamos a assinatura no Stripe. Registros de pagamento podem ser mantidos pelo Stripe pelo prazo exigido em lei.',
        ],
      },
      {
        titulo: '7. Seus direitos',
        paragrafos: [
          'Você pode, a qualquer momento: acessar e exportar seus dados (botão "Exportar meus dados" no perfil), corrigi-los (editar perfil), excluir a conta e tudo ligado a ela (botão "Excluir conta" no perfil), desligar os lembretes por e-mail (preferências) e revogar o consentimento.',
          'Para outros pedidos ou dúvidas, escreva para {contato}.',
        ],
      },
      {
        titulo: '8. Segurança',
        paragrafos: [
          'Senhas são guardadas com hash, a comunicação é criptografada (HTTPS), as sessões expiram e podem ser encerradas, e cada aluno só acessa os próprios dados. Nenhum sistema é totalmente imune a incidentes; se algum afetar seus dados, avisaremos pelo e-mail cadastrado.',
        ],
      },
    ],
  },
  en: {
    termos: [
      {
        titulo: '1. About BeReady',
        paragrafos: [
          'BeReady is an English study platform developed as an academic project (undergraduate thesis). By creating an account or signing in with a social network, you agree to these terms and to the privacy policy.',
        ],
      },
      {
        titulo: '2. Your account',
        paragrafos: [
          'You need to confirm your e-mail to use the account and you are responsible for keeping your password secret. Use real data and do not create accounts on behalf of other people.',
          'You can edit your data in your profile, export everything we store about you and delete your account whenever you want.',
        ],
      },
      {
        titulo: '3. Acceptable use',
        paragrafos: [
          'BeReady is for personal study. You may not use it to send illegal or offensive content or content that infringes the rights of others, try to access other users’ data, or automate access to overload the service.',
          'We may suspend accounts that break these rules.',
        ],
      },
      {
        titulo: '4. AI-generated content',
        paragrafos: [
          'Translations and similar sentences may be generated by an AI model (Claude, by Anthropic). The result may contain mistakes: use it to support your study, not as a definitive source. There is a daily generation limit per user.',
        ],
      },
      {
        titulo: '5. Plans and payments',
        paragrafos: [
          'The free plan has usage limits. The paid plan is a recurring subscription (monthly or yearly) processed by Stripe; BeReady never receives or stores your card details. You can cancel at any time: paid access continues until the end of the period already charged.',
        ],
      },
      {
        titulo: '6. Availability',
        paragrafos: [
          'As an academic project, the service is provided "as is", with no guarantee of uninterrupted operation. We may change features or shut the service down with prior notice to your registered e-mail.',
        ],
      },
      {
        titulo: '7. Changes and contact',
        paragrafos: [
          'If these terms change, the new version will be published on this page with its update date, and we will ask for a new acceptance when the change is relevant.',
          'Questions: {contato}.',
        ],
      },
    ],
    privacidade: [
      {
        titulo: '1. Who processes your data',
        paragrafos: [
          'This policy explains which personal data BeReady stores, why, and who it is shared with, under the Brazilian General Data Protection Law (LGPD, Law 13,709/2018). BeReady is an academic project; the privacy contact is {contato}.',
        ],
      },
      {
        titulo: '2. Data we collect',
        paragrafos: [
          'Sign-up and profile: name, e-mail, password (stored only as a hash, never as text), phone (optional), profile photo (optional), English level, preferred language and study goals.',
          'Study content: flashcards, tags, quizzes, prompts, translations, sentences and images you create or generate.',
          'Platform usage: answers to reviews and quizzes, progress, day streak, study time, pronunciation exercise scores (only the recognized text, not the audio), achievements, placement test result, preferences and the record of your acceptance of these terms.',
          'Subscription: plan, dates and payment status. Card details stay only with Stripe.',
        ],
      },
      {
        titulo: '3. How we use it',
        paragrafos: [
          'To create and maintain your account, show your progress, adapt content to your level, charge and manage the subscription, send service e-mails (e-mail confirmation, password recovery and study reminders, which can be turned off in preferences) and protect the platform against abuse.',
          'Legal bases: performance of the contract (providing the service you asked for), compliance with legal obligations and, for e-mail reminders and social sign-in, your consent, which you can withdraw at any time.',
        ],
      },
      {
        titulo: '4. Who we share it with',
        paragrafos: [
          'Stripe: processing subscription payments.',
          'Anthropic (Claude): the prompt text when you ask for an AI-generated translation or sentences.',
          'Cloudinary: storing your profile photo.',
          'Google, Facebook and LinkedIn: only if you choose to sign in with them; we receive name, e-mail and photo.',
          'Google reCAPTCHA: protecting sign-in and sign-up against bots.',
          'Browser speech recognition: in the pronunciation exercise, the browser (Google in Chrome, Microsoft in Edge) may send the audio to its servers to turn it into text. BeReady only receives the text.',
          'Database hosting, e-mail delivery and error monitoring providers hired to run the service.',
          'We do not sell your data or use it for advertising.',
        ],
      },
      {
        titulo: '5. Cookies and browser storage',
        paragrafos: [
          'We use one protected (httpOnly) technical cookie to keep you securely signed in, and we store your chosen language in the browser. We do not use tracking or advertising cookies.',
        ],
      },
      {
        titulo: '6. How long we keep it',
        paragrafos: [
          'For as long as your account exists. When you delete your account, we erase the profile and all study and usage content linked to it, and cancel the subscription at Stripe. Payment records may be kept by Stripe for the period required by law.',
        ],
      },
      {
        titulo: '7. Your rights',
        paragrafos: [
          'At any time you can: access and export your data ("Export my data" button in your profile), correct it (edit profile), delete your account and everything linked to it ("Delete account" button in your profile), turn off e-mail reminders (preferences) and withdraw consent.',
          'For other requests or questions, write to {contato}.',
        ],
      },
      {
        titulo: '8. Security',
        paragrafos: [
          'Passwords are stored as hashes, communication is encrypted (HTTPS), sessions expire and can be ended, and each student can only access their own data. No system is fully immune to incidents; if one affects your data, we will notify your registered e-mail.',
        ],
      },
    ],
  },
}
