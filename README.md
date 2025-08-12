# Gestión de Ejecutivos - Documentación

<img width="1361" height="653" alt="imagen" src="https://github.com/user-attachments/assets/7c8031ac-6df4-436c-be2c-07bfea48f5ca" />

Sistema completo para la gestión jerárquica de ejecutivos con capacidad de arrastrar y soltar elementos, edición en tiempo real y eliminación inteligente que mantiene la estructura organizacional.

## Características Principales

- 🌳 **Árbol jerárquico interactivo** con drag & drop
- 👨‍💼 **CRUD completo** de ejecutivos
- 🧠 **Eliminación inteligente** que reasigna subordinados automáticamente
- 📱 **Diseño responsive** y moderno
- 🔄 **Actualización en tiempo real** sin recargar la página
- 🎨 **Tema personalizable** en tonos cafés

## Requisitos Técnicos

- PHP 5.6
- MySQL 5.6+
- Servidor web (Apache, Nginx)
- jQuery 3.6.0
- Bootstrap 5.1.3
- jsTree 3.3.12

## Estructura de Base de Datos

```sql
CREATE TABLE ejecutivo (
    id_eje INT(11) AUTO_INCREMENT PRIMARY KEY,
    nom_eje VARCHAR(255) NOT NULL,
    tel_eje VARCHAR(15) NOT NULL,
    eli_eje INT DEFAULT 1,
    id_padre INT NULL,
    FOREIGN KEY (id_padre) REFERENCES ejecutivo(id_eje) ON DELETE SET NULL
) ENGINE=InnoDB;
```

## Estructura de Archivos

/proyecto/ <br>
│── db.sql                # Script de creación de base de datos <br>
│── index.php             # Aplicación principal (backend + frontend) <br>
└── README.md             # Este archivo <br>
