<?php

return [
    'kicker' => 'Informações legais',
    'title' => 'Política de privacidade e termos de uso',
    'meta_description' => 'Política de privacidade e termos de uso da plataforma Nossa Casa.',
    'back_to_portal' => 'Voltar ao portal',
    'updated_at' => 'Última atualização: 22 de agosto de 2026',
    'introduction' => 'O Nossa Casa é um projeto comunitário criado para ajudar igrejas a se comunicarem, organizarem eventos e servirem seus membros. Esta página explica como a plataforma pode ser utilizada e como as informações são tratadas.',
    'sections' => [
        [
            'title' => '1. Aceitação e finalidade',
            'paragraphs' => [
                'Ao acessar o Nossa Casa, você concorda em utilizar a plataforma de forma legal, respeitosa e coerente com a missão da igreja ou comunidade que administra o espaço.',
                'A plataforma pode oferecer páginas públicas, áreas privadas, eventos, formulários, conteúdos bíblicos, check-in e checkout infantil, comentários, mídias e transmissões ao vivo.',
            ],
            'items' => [],
        ],
        [
            'title' => '2. Informações tratadas',
            'paragraphs' => [
                'Conforme os recursos habilitados pela igreja, a plataforma pode tratar dados da conta, informações de contato, vínculo com a igreja, inscrições em eventos, respostas de formulários, comentários, registros de check-in e checkout, mídias e logs técnicos.',
                'As igrejas são responsáveis por decidir quais informações coletam e por utilizá-las apenas para finalidades legítimas de ministério, administração e segurança.',
            ],
            'items' => [
                'Coletamos somente as informações necessárias para o recurso utilizado ou para a segurança e operação do serviço.',
                'Informações sensíveis não devem ser enviadas em comentários públicos, formulários públicos ou chat ao vivo.',
                'Os dados de check-in infantil devem ser tratados por responsáveis autorizados e usados somente para cuidado, identificação e liberação segura.',
            ],
        ],
        [
            'title' => '3. Armazenamento, segurança e acesso',
            'paragraphs' => [
                'Os dados são armazenados pelo operador da aplicação ou pela igreja que mantém sua própria instalação. As mídias podem ser armazenadas em serviços compatíveis com S3, como o MinIO, e entregues por URLs temporárias ou assinadas.',
                'Aplicamos controles de acesso, autenticação, segredos criptografados e medidas operacionais adequadas à instalação. Nenhum serviço de internet pode garantir segurança absoluta; por isso, credenciais nunca devem ser compartilhadas ou enviadas ao repositório.',
            ],
            'items' => [
                'Administradores da igreja podem gerenciar seus próprios registros dentro do escopo de autorização.',
                'Operadores do sistema podem acessar dados de infraestrutura quando necessário para fornecer, proteger, manter ou recuperar o serviço.',
                'Os dados podem ser mantidos enquanto forem necessários para a igreja, obrigações legais, backups ou investigações de segurança.',
            ],
        ],
        [
            'title' => '4. Transmissões, comentários e mídias',
            'paragraphs' => [
                'Transmissões, comentários e mídias marcados como públicos podem ser visualizados, copiados ou redistribuídos por outras pessoas. Não publique informações pessoais, confidenciais ou sensíveis em áreas públicas.',
                'A igreja ou um moderador autorizado pode remover comentários, mídias ou transmissões que violem suas regras, estes termos ou a legislação aplicável.',
            ],
            'items' => [],
        ],
        [
            'title' => '5. Bíblia e conteúdos de terceiros',
            'paragraphs' => [
                'As versões da Bíblia e outros materiais da biblioteca continuam sujeitos aos direitos e licenças de seus respectivos autores e fornecedores. Utilize-os somente dentro das permissões exibidas pela aplicação ou pela licença de origem.',
                'O projeto não reivindica a propriedade de conteúdos de terceiros apenas por disponibilizá-los em um recurso.',
            ],
            'items' => [],
        ],
        [
            'title' => '6. Disponibilidade e alterações',
            'paragraphs' => [
                'O Nossa Casa é desenvolvido como um projeto comunitário e pode mudar, ficar indisponível para manutenção ou ter recursos habilitados ou desabilitados por cada instalação. Nenhuma disponibilidade ou prazo de retenção de dados é garantido, salvo acordo específico em contrário.',
                'Esta política e estes termos podem ser atualizados quando o projeto, as leis ou as práticas operacionais mudarem. A página localizada é gerada a partir do conteúdo-fonte atual e pode ser traduzida pelo fluxo de tradução do projeto.',
            ],
            'items' => [],
        ],
        [
            'title' => '7. Contato e solicitações',
            'paragraphs' => [
                'Para dúvidas, pedidos de correção, exclusão ou preocupações sobre uma instalação de igreja, entre em contato com a igreja responsável por essa instalação. Para o projeto público, use o sistema de issues do repositório ou o canal de contato publicado pelos mantenedores.',
            ],
            'items' => [],
        ],
    ],
];
