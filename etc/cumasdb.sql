--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = public, pg_catalog;

--
-- Name: attach_tab_at_id_seq; Type: SEQUENCE; Schema: public; Owner: cumas
--

CREATE SEQUENCE attach_tab_at_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.attach_tab_at_id_seq OWNER TO cumas;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: attach_tab; Type: TABLE; Schema: public; Owner: cumas; Tablespace: 
--

CREATE TABLE attach_tab (
    at_id integer DEFAULT nextval('attach_tab_at_id_seq'::regclass) NOT NULL,
    at_mailid integer,
    at_filename character varying,
    at_filepath character varying,
    at_mimetypes character varying
);


ALTER TABLE public.attach_tab OWNER TO cumas;

--
-- Name: category_tab; Type: TABLE; Schema: public; Owner: cumas; Tablespace: 
--

CREATE TABLE category_tab (
    ca_id integer NOT NULL,
    ca_name character varying(64),
    ca_ident character varying(64),
    ca_active boolean
);


ALTER TABLE public.category_tab OWNER TO cumas;

--
-- Name: category_tab_ca_id_seq; Type: SEQUENCE; Schema: public; Owner: cumas
--

CREATE SEQUENCE category_tab_ca_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.category_tab_ca_id_seq OWNER TO cumas;

--
-- Name: category_tab_ca_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: cumas
--

ALTER SEQUENCE category_tab_ca_id_seq OWNED BY category_tab.ca_id;


--
-- Name: contact_mail_tab; Type: TABLE; Schema: public; Owner: cumas; Tablespace: 
--

CREATE TABLE contact_mail_tab (
    co_id integer,
    ma_id integer
);


ALTER TABLE public.contact_mail_tab OWNER TO cumas;

--
-- Name: contact_tab; Type: TABLE; Schema: public; Owner: cumas; Tablespace: 
--

CREATE TABLE contact_tab (
    co_id integer NOT NULL,
    co_us_id integer,
    co_inquiry timestamp without time zone,
    co_start timestamp without time zone,
    co_complete timestamp without time zone,
    co_lastupdate timestamp without time zone,
    co_limit timestamp without time zone,
    co_status integer DEFAULT 0,
    co_comment character varying(2048),
    co_operator integer,
    co_parent integer,
    co_child_no integer,
    co_ma_id integer,
    ca_id integer
);


ALTER TABLE public.contact_tab OWNER TO cumas;

--
-- Name: contact_tab_co_id_seq; Type: SEQUENCE; Schema: public; Owner: cumas
--

CREATE SEQUENCE contact_tab_co_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.contact_tab_co_id_seq OWNER TO cumas;

--
-- Name: contact_tab_co_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: cumas
--

ALTER SEQUENCE contact_tab_co_id_seq OWNED BY contact_tab.co_id;


--
-- Name: mail_tab; Type: TABLE; Schema: public; Owner: cumas; Tablespace: 
--

CREATE TABLE mail_tab (
    ma_id integer NOT NULL,
    ma_message_id character varying(64),
    ma_reference_id character varying(64),
    ma_date timestamp without time zone,
    ma_from_addr character varying(255),
    ma_subject character varying,
    ma_to_addr text,
    ma_cc_addr text
);


ALTER TABLE public.mail_tab OWNER TO cumas;

--
-- Name: mail_tab_ma_id_seq; Type: SEQUENCE; Schema: public; Owner: cumas
--

CREATE SEQUENCE mail_tab_ma_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.mail_tab_ma_id_seq OWNER TO cumas;

--
-- Name: mail_tab_ma_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: cumas
--

ALTER SEQUENCE mail_tab_ma_id_seq OWNED BY mail_tab.ma_id;


--
-- Name: status_tab; Type: TABLE; Schema: public; Owner: cumas; Tablespace: 
--

CREATE TABLE status_tab (
    st_id integer,
    st_status character varying(32),
    st_color character varying
);


ALTER TABLE public.status_tab OWNER TO cumas;

--
-- Name: task_tab_ta_id_seq; Type: SEQUENCE; Schema: public; Owner: cumas
--

CREATE SEQUENCE task_tab_ta_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.task_tab_ta_id_seq OWNER TO cumas;

--
-- Name: task_tab; Type: TABLE; Schema: public; Owner: cumas; Tablespace: 
--

CREATE TABLE task_tab (
    ta_id integer DEFAULT nextval('task_tab_ta_id_seq'::regclass) NOT NULL,
    ta_category integer,
    ta_user integer,
    ta_registuser integer,
    ta_registdate timestamp without time zone,
    ta_post timestamp without time zone,
    ta_repmode integer,
    ta_repday integer,
    ta_subject character varying,
    ta_body text,
    ta_comment text
);


ALTER TABLE public.task_tab OWNER TO cumas;

--
-- Name: user_tab; Type: TABLE; Schema: public; Owner: cumas; Tablespace: 
--

CREATE TABLE user_tab (
    us_id integer NOT NULL,
    us_name character varying(64),
    us_mail character varying(255),
    us_login_name character varying(64),
    us_login_passwd character varying(64),
    us_active boolean,
    us_admin_flg boolean
);


ALTER TABLE public.user_tab OWNER TO cumas;

--
-- Name: user_tab_us_id_seq; Type: SEQUENCE; Schema: public; Owner: cumas
--

CREATE SEQUENCE user_tab_us_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.user_tab_us_id_seq OWNER TO cumas;

--
-- Name: user_tab_us_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: cumas
--

ALTER SEQUENCE user_tab_us_id_seq OWNED BY user_tab.us_id;


--
-- Name: ca_id; Type: DEFAULT; Schema: public; Owner: cumas
--

ALTER TABLE ONLY category_tab ALTER COLUMN ca_id SET DEFAULT nextval('category_tab_ca_id_seq'::regclass);


--
-- Name: co_id; Type: DEFAULT; Schema: public; Owner: cumas
--

ALTER TABLE ONLY contact_tab ALTER COLUMN co_id SET DEFAULT nextval('contact_tab_co_id_seq'::regclass);


--
-- Name: ma_id; Type: DEFAULT; Schema: public; Owner: cumas
--

ALTER TABLE ONLY mail_tab ALTER COLUMN ma_id SET DEFAULT nextval('mail_tab_ma_id_seq'::regclass);


--
-- Name: us_id; Type: DEFAULT; Schema: public; Owner: cumas
--

ALTER TABLE ONLY user_tab ALTER COLUMN us_id SET DEFAULT nextval('user_tab_us_id_seq'::regclass);


--
-- Data for Name: attach_tab; Type: TABLE DATA; Schema: public; Owner: cumas
--

COPY attach_tab (at_id, at_mailid, at_filename, at_filepath, at_mimetypes) FROM stdin;
\.


--
-- Name: attach_tab_at_id_seq; Type: SEQUENCE SET; Schema: public; Owner: cumas
--

SELECT pg_catalog.setval('attach_tab_at_id_seq', 1, false);


--
-- Data for Name: category_tab; Type: TABLE DATA; Schema: public; Owner: cumas
--

COPY category_tab (ca_id, ca_name, ca_ident, ca_active) FROM stdin;
\.


--
-- Name: category_tab_ca_id_seq; Type: SEQUENCE SET; Schema: public; Owner: cumas
--

SELECT pg_catalog.setval('category_tab_ca_id_seq', 6, true);


--
-- Data for Name: contact_mail_tab; Type: TABLE DATA; Schema: public; Owner: cumas
--

COPY contact_mail_tab (co_id, ma_id) FROM stdin;
\.


--
-- Data for Name: contact_tab; Type: TABLE DATA; Schema: public; Owner: cumas
--

COPY contact_tab (co_id, co_us_id, co_inquiry, co_start, co_complete, co_lastupdate, co_limit, co_status, co_comment, co_operator, co_parent, co_child_no, co_ma_id, ca_id) FROM stdin;
\.


--
-- Name: contact_tab_co_id_seq; Type: SEQUENCE SET; Schema: public; Owner: cumas
--

SELECT pg_catalog.setval('contact_tab_co_id_seq', 454, true);


--
-- Data for Name: mail_tab; Type: TABLE DATA; Schema: public; Owner: cumas
--

COPY mail_tab (ma_id, ma_message_id, ma_reference_id, ma_date, ma_from_addr, ma_subject, ma_to_addr, ma_cc_addr) FROM stdin;
\.


--
-- Name: mail_tab_ma_id_seq; Type: SEQUENCE SET; Schema: public; Owner: cumas
--

SELECT pg_catalog.setval('mail_tab_ma_id_seq', 498, true);


--
-- Data for Name: status_tab; Type: TABLE DATA; Schema: public; Owner: cumas
--

COPY status_tab (st_id, st_status, st_color) FROM stdin;
0	未着手	#FF0000
1	対応中	#FF6600
2	保留中	#00CC00
3	お客様確認中	#0000FF
4	完了	#000000
5	連絡済	#00CCCC
6	連絡不要	#9900FF
\.


--
-- Data for Name: task_tab; Type: TABLE DATA; Schema: public; Owner: cumas
--

COPY task_tab (ta_id, ta_category, ta_user, ta_registuser, ta_registdate, ta_post, ta_repmode, ta_repday, ta_subject, ta_body, ta_comment) FROM stdin;
\.


--
-- Name: task_tab_ta_id_seq; Type: SEQUENCE SET; Schema: public; Owner: cumas
--

SELECT pg_catalog.setval('task_tab_ta_id_seq', 1, false);


--
-- Data for Name: user_tab; Type: TABLE DATA; Schema: public; Owner: cumas
--

COPY user_tab (us_id, us_name, us_mail, us_login_name, us_login_passwd, us_active, us_admin_flg) FROM stdin;
1	初期管理者	default_admin@example.com	admin	$1$VM.elAOW$J9kgBe9g0ZbNQtgPUwmk4.	t	t
\.


--
-- Name: user_tab_us_id_seq; Type: SEQUENCE SET; Schema: public; Owner: cumas
--

SELECT pg_catalog.setval('user_tab_us_id_seq', 7, true);


--
-- Name: category_tab_ca_ident_key; Type: CONSTRAINT; Schema: public; Owner: cumas; Tablespace: 
--

ALTER TABLE ONLY category_tab
    ADD CONSTRAINT category_tab_ca_ident_key UNIQUE (ca_ident);


--
-- Name: category_tab_ca_name_key; Type: CONSTRAINT; Schema: public; Owner: cumas; Tablespace: 
--

ALTER TABLE ONLY category_tab
    ADD CONSTRAINT category_tab_ca_name_key UNIQUE (ca_name);


--
-- Name: category_tab_pkey; Type: CONSTRAINT; Schema: public; Owner: cumas; Tablespace: 
--

ALTER TABLE ONLY category_tab
    ADD CONSTRAINT category_tab_pkey PRIMARY KEY (ca_id);


--
-- Name: contact_tab_co_id_key; Type: CONSTRAINT; Schema: public; Owner: cumas; Tablespace: 
--

ALTER TABLE ONLY contact_tab
    ADD CONSTRAINT contact_tab_co_id_key UNIQUE (co_id);


--
-- Name: mail_tab_ma_id_key; Type: CONSTRAINT; Schema: public; Owner: cumas; Tablespace: 
--

ALTER TABLE ONLY mail_tab
    ADD CONSTRAINT mail_tab_ma_id_key UNIQUE (ma_id);


--
-- Name: status_tab_st_id_key; Type: CONSTRAINT; Schema: public; Owner: cumas; Tablespace: 
--

ALTER TABLE ONLY status_tab
    ADD CONSTRAINT status_tab_st_id_key UNIQUE (st_id);


--
-- Name: user_tab_us_id_key; Type: CONSTRAINT; Schema: public; Owner: cumas; Tablespace: 
--

ALTER TABLE ONLY user_tab
    ADD CONSTRAINT user_tab_us_id_key UNIQUE (us_id);


--
-- Name: user_tab_us_login_name_key; Type: CONSTRAINT; Schema: public; Owner: cumas; Tablespace: 
--

ALTER TABLE ONLY user_tab
    ADD CONSTRAINT user_tab_us_login_name_key UNIQUE (us_login_name);


--
-- Name: user_tab_us_mail_key; Type: CONSTRAINT; Schema: public; Owner: cumas; Tablespace: 
--

ALTER TABLE ONLY user_tab
    ADD CONSTRAINT user_tab_us_mail_key UNIQUE (us_mail);


--
-- Name: user_tab_us_name_key; Type: CONSTRAINT; Schema: public; Owner: cumas; Tablespace: 
--

ALTER TABLE ONLY user_tab
    ADD CONSTRAINT user_tab_us_name_key UNIQUE (us_name);


--
-- Name: attach_tab_at_mailid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: cumas
--

ALTER TABLE ONLY attach_tab
    ADD CONSTRAINT attach_tab_at_mailid_fkey FOREIGN KEY (at_mailid) REFERENCES mail_tab(ma_id);


--
-- Name: contact_mail_tab_co_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: cumas
--

ALTER TABLE ONLY contact_mail_tab
    ADD CONSTRAINT contact_mail_tab_co_id_fkey FOREIGN KEY (co_id) REFERENCES contact_tab(co_id);


--
-- Name: contact_mail_tab_ma_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: cumas
--

ALTER TABLE ONLY contact_mail_tab
    ADD CONSTRAINT contact_mail_tab_ma_id_fkey FOREIGN KEY (ma_id) REFERENCES mail_tab(ma_id);


--
-- Name: contact_tab_ca_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: cumas
--

ALTER TABLE ONLY contact_tab
    ADD CONSTRAINT contact_tab_ca_id_fkey FOREIGN KEY (ca_id) REFERENCES category_tab(ca_id);


--
-- Name: contact_tab_co_ma_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: cumas
--

ALTER TABLE ONLY contact_tab
    ADD CONSTRAINT contact_tab_co_ma_id_fkey FOREIGN KEY (co_ma_id) REFERENCES mail_tab(ma_id);


--
-- Name: contact_tab_co_operator_fkey; Type: FK CONSTRAINT; Schema: public; Owner: cumas
--

ALTER TABLE ONLY contact_tab
    ADD CONSTRAINT contact_tab_co_operator_fkey FOREIGN KEY (co_operator) REFERENCES user_tab(us_id);


--
-- Name: contact_tab_co_status_fkey; Type: FK CONSTRAINT; Schema: public; Owner: cumas
--

ALTER TABLE ONLY contact_tab
    ADD CONSTRAINT contact_tab_co_status_fkey FOREIGN KEY (co_status) REFERENCES status_tab(st_id);


--
-- Name: contact_tab_co_us_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: cumas
--

ALTER TABLE ONLY contact_tab
    ADD CONSTRAINT contact_tab_co_us_id_fkey FOREIGN KEY (co_us_id) REFERENCES user_tab(us_id);


--
-- Name: task_tab_ta_category_fkey; Type: FK CONSTRAINT; Schema: public; Owner: cumas
--

ALTER TABLE ONLY task_tab
    ADD CONSTRAINT task_tab_ta_category_fkey FOREIGN KEY (ta_category) REFERENCES category_tab(ca_id);


--
-- Name: task_tab_ta_registuser_fkey; Type: FK CONSTRAINT; Schema: public; Owner: cumas
--

ALTER TABLE ONLY task_tab
    ADD CONSTRAINT task_tab_ta_registuser_fkey FOREIGN KEY (ta_registuser) REFERENCES user_tab(us_id);


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--


--
-- PostgreSQL database dump complete
--

