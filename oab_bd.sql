-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 25/03/2026 às 18:47
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `oab_bd`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `admin_usuarios`
--

CREATE TABLE `admin_usuarios` (
  `id` int(11) NOT NULL,
  `nome_completo` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `cpf` varchar(11) NOT NULL,
  `nivel_acesso` enum('admin','editor','leitor') NOT NULL DEFAULT 'leitor',
  `senha` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `admin_usuarios`
--

INSERT INTO `admin_usuarios` (`id`, `nome_completo`, `email`, `cpf`, `nivel_acesso`, `senha`, `created_at`, `updated_at`) VALUES
(1, 'Erik Primão Silva', 'erikprimao@gmail.com', '35857206847', 'admin', '$2y$10$9birO8iAe8y7ABKWImziNOUcEfxtJ1LUBHW9H2JZ0MHsJrtRkUPjO', '2026-03-24 00:41:35', '2026-03-24 02:56:16'),
(2, 'Erik Primao Silva', 'erikpsilva@gmail.com', '07560931804', 'editor', '$2y$10$S3YUxfGgw4bz00aRT54dpOXNpPL90hXDFt3qzbjvFJATMkSoya3RC', '2026-03-24 02:47:50', '2026-03-24 02:47:50'),
(3, 'Ana Paula Aparecida Rodrigues Assunção', 'anapaula.nutri11@gmail.com', '34044956839', 'leitor', '$2y$10$HaNR2fZfJfX23SFPblcRtu24h5y4ujP8PUFjnJ0DaET5jqSRrpQf.', '2026-03-24 02:49:59', '2026-03-24 02:49:59');

-- --------------------------------------------------------

--
-- Estrutura para tabela `app_configuracoes`
--

CREATE TABLE `app_configuracoes` (
  `chave` varchar(50) NOT NULL,
  `valor` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `app_configuracoes`
--

INSERT INTO `app_configuracoes` (`chave`, `valor`) VALUES
('disparo_dia_semana', '4'),
('disparo_hora', '18:00'),
('email_admin', 'erikprimao@gmail.com'),
('email_esperia', 'erikpsilva@gmail.com');

-- --------------------------------------------------------

--
-- Estrutura para tabela `confirmacoes_treino`
--

CREATE TABLE `confirmacoes_treino` (
  `id` int(10) UNSIGNED NOT NULL,
  `jogador_id` int(10) UNSIGNED NOT NULL,
  `data_treino` date NOT NULL,
  `nome_completo` varchar(150) NOT NULL,
  `cpf` char(11) NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  `confirmado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `confirmacoes_treino`
--

INSERT INTO `confirmacoes_treino` (`id`, `jogador_id`, `data_treino`, `nome_completo`, `cpf`, `telefone`, `email`, `confirmado_em`) VALUES
(1, 1, '2026-03-27', 'Erik Primão Silva', '35857206847', '11942307240', 'erikprimao@gmail.com', '2026-03-25 05:04:01'),
(2, 2, '2026-03-27', 'Ana Paula Aparecida Rodrigues Assunção', '34044956839', '11942307240', 'anapaula.nutri11@gmail.com', '2026-03-25 05:57:23');

-- --------------------------------------------------------

--
-- Estrutura para tabela `jogadores`
--

CREATE TABLE `jogadores` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome_completo` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `cpf` char(11) NOT NULL,
  `data_nascimento` date NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `jogadores`
--

INSERT INTO `jogadores` (`id`, `nome_completo`, `email`, `cpf`, `data_nascimento`, `telefone`, `senha`, `criado_em`) VALUES
(1, 'Erik Primão Silva', 'erikprimao@gmail.com', '35857206847', '1989-07-01', '11942307240', '$2y$10$8jxLKZVVcLXmbyia3pbxDeaYF3joSe0S8HsdehpdzwLsdFQcduSkC', '2026-03-25 03:53:23'),
(2, 'Ana Paula Aparecida Rodrigues Assunção', 'anapaula.nutri11@gmail.com', '34044956839', '1989-07-01', '11942307240', '$2y$10$7Q9aCZqktDIgMuqzeAzMZeWzSekZZGYZW.AXUEpiano4wVQaLgrru', '2026-03-25 04:36:30'),
(3, 'Erik Silveira', 'erikprsilva@gmail.com', '07560931804', '1989-07-01', '11906534654', '$2y$10$CIdNVQ4aglr.ej3WrhMzAuLPM4YinDOSxqffVhHbyGw6SHph4wliC', '2026-03-25 04:38:39');

-- --------------------------------------------------------

--
-- Estrutura para tabela `treinos_encerrados`
--

CREATE TABLE `treinos_encerrados` (
  `data_treino` date NOT NULL,
  `encerrado_at` datetime NOT NULL DEFAULT current_timestamp(),
  `auto_enviado` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `data_nascimento` date NOT NULL,
  `cpf` varchar(11) NOT NULL,
  `telefone` varchar(15) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `data_nascimento`, `cpf`, `telefone`, `senha`, `created_at`, `updated_at`) VALUES
(1, 'João Silva', 'joao.silva@teste.com', '1990-06-15', '52998224725', '11987654321', '$2y$10$KUJWG3.JQ4Kf3AvazaL6tOKTAWRQWyvY2djp85.1a4YAhdpUMFq1W', '2026-03-24 03:10:20', '2026-03-24 03:10:20');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `admin_usuarios`
--
ALTER TABLE `admin_usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `cpf` (`cpf`);

--
-- Índices de tabela `app_configuracoes`
--
ALTER TABLE `app_configuracoes`
  ADD PRIMARY KEY (`chave`);

--
-- Índices de tabela `confirmacoes_treino`
--
ALTER TABLE `confirmacoes_treino`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_jogador_treino` (`jogador_id`,`data_treino`);

--
-- Índices de tabela `jogadores`
--
ALTER TABLE `jogadores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `cpf` (`cpf`);

--
-- Índices de tabela `treinos_encerrados`
--
ALTER TABLE `treinos_encerrados`
  ADD PRIMARY KEY (`data_treino`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `cpf` (`cpf`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `admin_usuarios`
--
ALTER TABLE `admin_usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `confirmacoes_treino`
--
ALTER TABLE `confirmacoes_treino`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `jogadores`
--
ALTER TABLE `jogadores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
